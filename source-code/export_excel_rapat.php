<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (file_exists(__DIR__ . '/../koneksi.php')) {
    include __DIR__ . '/../koneksi.php';
} elseif (file_exists(__DIR__ . '/../../koneksi.php')) {
    include __DIR__ . '/../../koneksi.php';
} else {
    include 'koneksi.php';
}

$roleUser = $_SESSION['role'] ?? '';
$satker   = $_SESSION['kode_satker'] ?? ($_SESSION['satker'] ?? 694762);
$nipUser  = $_SESSION['nip'] ?? '';

if (!$roleUser || !$nipUser) {
    die('Akses tidak valid');
}

$allowedRoles = [
    'admin',
    'staf khusus',
    'staf umum',
    'user',
    'koordinator',
    'lainnya'
];

if (!in_array($roleUser, $allowedRoles, true)) {
    die('Anda tidak memiliki akses export.');
}

$unitUser = '';

$qUnitUser = $conn->prepare("
    SELECT UNIT_II
    FROM tb_pegawai
    WHERE nip_pegawai = ?
    LIMIT 1
");
$qUnitUser->bind_param("s", $nipUser);
$qUnitUser->execute();
$resUnitUser = $qUnitUser->get_result()->fetch_assoc();

if ($resUnitUser) {
    $unitUser = $resUnitUser['UNIT_II'] ?? '';
}

$unitSafe = $conn->real_escape_string($unitUser);

$tahun = isset($_GET['tahun']) && is_numeric($_GET['tahun'])
    ? (int)$_GET['tahun']
    : date('Y');

$bulan = isset($_GET['bulan']) && is_numeric($_GET['bulan'])
    ? (int)$_GET['bulan']
    : '';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$qSafe = $conn->real_escape_string($q);

$where = "
WHERE YEAR(ar.tanggal) = '$tahun'
AND ar.status = 1
";

if ($roleUser === 'admin' || $roleUser === 'staf umum') {
    $where .= " AND ar.id_pertemuan IN (1, 2)";

} elseif ($roleUser === 'staf khusus') {
    $where .= "
        AND ar.id_pertemuan = 2
        AND LOWER(TRIM(ar.penyelenggara)) = LOWER(TRIM('$unitSafe'))
    ";

} elseif ($roleUser === 'user') {
    $nipSafe = $conn->real_escape_string($nipUser);

    $where .= "
        AND (
            ar.nip_pemohon = '$nipSafe'
            OR FIND_IN_SET('$nipSafe', REPLACE(ar.nama_pelaksana, ' ', ''))
            OR FIND_IN_SET('$nipSafe', REPLACE(ar.pendamping, ' ', ''))
        )
    ";

} elseif ($roleUser === 'koordinator' || $roleUser === 'lainnya') {
    $where .= "
        AND (
            ar.id_pertemuan = 1
            OR LOWER(TRIM(ar.penyelenggara)) = LOWER(TRIM('$unitSafe'))
        )
    ";
}

if ($bulan !== '') {
    $where .= " AND MONTH(ar.tanggal) = '$bulan'";
}

if ($q !== '') {
    $where .= " AND (
        ar.topik_rapat LIKE '%$qSafe%' OR
        ar.nama_ruang LIKE '%$qSafe%' OR
        ar.penyelenggara LIKE '%$qSafe%' OR
        ar.kategori_rapat LIKE '%$qSafe%' OR
        ar.meeting_type LIKE '%$qSafe%' OR
        ar.narahubung LIKE '%$qSafe%'
    )";
}

if (!empty($satker)) {
    $satkerSafe = $conn->real_escape_string($satker);
    $where .= " AND r.kd_satker = '$satkerSafe'";
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Daftar_Agenda_{$tahun}_" . date('Y-m-d_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$sql = "
SELECT 
    ar.*,
    ar.nama_ruang,
    GROUP_CONCAT(DISTINCT b.spek_bmn ORDER BY b.spek_bmn SEPARATOR ', ') AS fasilitas_nama,
    COALESCE(p.nama_pegawai, u.nama, '-') AS nama_pemohon,

    (
        SELECT GROUP_CONCAT(pg.nama_pegawai SEPARATOR ', ')
        FROM tb_pegawai pg
        WHERE FIND_IN_SET(pg.nip_pegawai, REPLACE(ar.nama_pelaksana, ' ', ''))
    ) AS nama_pelaksana_text,

    (
        SELECT GROUP_CONCAT(pd.nama_pegawai SEPARATOR ', ')
        FROM tb_pegawai pd
        WHERE FIND_IN_SET(pd.nip_pegawai, REPLACE(ar.pendamping, ' ', ''))
    ) AS pendamping_text

FROM tb_agenda_rapat ar

LEFT JOIN tb_ruangan_rapat r 
    ON FIND_IN_SET(r.id_ruang, ar.id_ruang_rapat)

LEFT JOIN tb_pegawai p 
    ON ar.nip_pemohon = p.nip_pegawai

LEFT JOIN tb_user u
    ON ar.nip_pemohon = u.nip

LEFT JOIN pengajuan_bmn pb
    ON pb.id_agenda = ar.id

LEFT JOIN pengajuan_bmn_detail pbd
    ON pbd.id_pengajuan = pb.id_pengajuan

LEFT JOIN daftar_bmn b
    ON b.id_bmn = pbd.id_bmn

$where

GROUP BY ar.id
ORDER BY ar.tanggal DESC, ar.waktu_mulai DESC
";

$result = $conn->query($sql);

if (!$result) {
    die('Query export gagal: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width:100%; }
        th, td { border:1px solid #000; padding:6px; vertical-align: top; }
        th { background:#22466C; color:#fff; }
    </style>
</head>
<body>
    <h3>DAFTAR AGENDA TAHUN <?= htmlspecialchars($tahun) ?></h3>
    <p>Diexport: <?= date('d F Y H:i:s') ?></p>
    <p>Role: <?= htmlspecialchars($roleUser) ?></p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Jenis Agenda</th>
                <th>Tanggal</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Kategori</th>
                <th>Topik</th>
                <th>Tempat/Ruang</th>
                <th>Penyelenggara</th>
                <th>Peserta</th>
                <th>Pemohon</th>
                <th>Narahubung</th>
                <th>Jenis Pertemuan</th>
                <th>Link</th>
                <th>Fasilitas</th>
                <th>Konsumsi</th>
                <th>Kehadiran</th>
                <th>Pelaksana</th>
                <th>Pendamping</th>
                <th>Keterangan</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = $result->fetch_assoc()):
                $status = $row['status'] == 1 ? 'Aktif' : 'Batal';
                $jenisAgenda = ((int)$row['id_pertemuan'] === 1) ? 'Agenda Rapat' : 'Agenda Kegiatan';
                $konsumsi = ((int)$row['ajukan_konsumsi'] === 1)
                    ? ($row['catatan_konsumsi'] ?: 'Ya')
                    : 'Tidak';
            ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($jenisAgenda) ?></td>
                    <td><?= htmlspecialchars($row['tanggal']) ?></td>
                    <td><?= htmlspecialchars($row['waktu_mulai']) ?></td>
                    <td><?= htmlspecialchars($row['waktu_selesai']) ?></td>
                    <td><?= htmlspecialchars($row['kategori_rapat'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['topik_rapat'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['nama_ruang'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['penyelenggara'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['jumlah_peserta'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['nama_pemohon'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['narahubung'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['meeting_type'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['link_zoom'] ?? ($row['online_link'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars($row['fasilitas_nama'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($konsumsi) ?></td>
                    <td><?= htmlspecialchars($row['kehadiran'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['nama_pelaksana_text'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['pendamping_text'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($status) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>