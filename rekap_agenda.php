<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  date_default_timezone_set('Asia/Jakarta');

  include "koneksi.php";

  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  if ($_SERVER['SERVER_NAME'] === 'localhost') {
    $_SESSION['role'] = 'admin';
    // $_SESSION['kode_satker'] = 694762;
    $_SESSION['nip'] = '3175066106030005';
  }

  $roleUser = $_SESSION['role'] ?? 'standalone';
  $satker   = $_SESSION['kode_satker'] ?? 694762;
  $nipUser  = $_SESSION['nip'] ?? '';

  if (empty($nipUser)) {
    die("NIP tidak ditemukan di session");
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
  $allowedRoles = ['admin', 'staf khusus','staf umum','user','koordinator','lainnya'];

  if (!in_array($roleUser, $allowedRoles, true)) {
    header("Location: /profil");
    exit;
  }

  $bulan = isset($_GET['bulan']) && is_numeric($_GET['bulan']) ? (int) $_GET['bulan'] : '';
  $tahun = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? (int) $_GET['tahun'] : date('Y');
  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  $qSafe = $conn->real_escape_string($q);
  $limit  = 10;
  $page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
  $offset = ($page - 1) * $limit;

  $where = "
    WHERE YEAR(rp.tanggal) = '$tahun'
    AND rp.status = 1
  ";

  if ($roleUser === 'admin' || $roleUser === 'staf umum') {
    $where .= " AND rp.id_pertemuan IN (1, 2)";
  } elseif ($roleUser === 'staf khusus') {
    $where .= "
      AND LOWER(TRIM(rp.penyelenggara)) = LOWER(TRIM('$unitSafe'))
    ";
  } elseif ($roleUser === 'user') {
    $where .= "
      AND (
        rp.nip_pemohon = '$nipUser'
        OR FIND_IN_SET('$nipUser', REPLACE(rp.nama_pelaksana, ' ', ''))
        OR FIND_IN_SET('$nipUser', REPLACE(rp.pendamping, ' ', ''))
      )
    ";
  } elseif ($roleUser === 'koordinator' || $roleUser === 'lainnya') {
    $where .= "
      AND (
        rp.id_pertemuan = 1
        OR LOWER(TRIM(rp.penyelenggara)) = LOWER(TRIM('$unitSafe'))
      )
    ";
  }
  if ($bulan !== '') {
    $where .= " AND MONTH(rp.tanggal) = '$bulan'";
  }
  if ($q !== '') {
    $where .= " AND (
      rp.topik_rapat LIKE '%$qSafe%' OR
      rp.nama_ruang LIKE '%$qSafe%' OR
      rp.penyelenggara LIKE '%$qSafe%' OR
      rp.kategori_rapat LIKE '%$qSafe%' OR
      rp.meeting_type LIKE '%$qSafe%' OR
      rp.narahubung LIKE '%$qSafe%'
    )";
  }
  if (!empty($satker)) {
    $where .= " AND r.kd_satker = '$satker'";
  }
  function uploadDokumen($fileInput, $folder = 'dokumen') {
    if (
      empty($_FILES[$fileInput]) ||
      empty($_FILES[$fileInput]['name']) ||
      empty($_FILES[$fileInput]['tmp_name']) ||
      !is_uploaded_file($_FILES[$fileInput]['tmp_name'])
    ) {
      return null;
    }
    if (!is_dir($folder)) {
      mkdir($folder, 0777, true);
    }
    $fileSize = $_FILES[$fileInput]['size'];
    $fileErr  = $_FILES[$fileInput]['error'];
    if ($fileErr !== UPLOAD_ERR_OK || $fileSize <= 0) {
      return null;
    }
    if ($fileSize > 5 * 1024 * 1024) {
      return null;
    }
    $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($ext, $allowed, true)) {
      return null;
    }
    $uniqueName = uniqid('doc_', true) . '.' . $ext;
    $path = $folder . '/' . $uniqueName;
    return move_uploaded_file($_FILES[$fileInput]['tmp_name'], $path)
      ? $path
      : null;
  }
  if (isset($_POST['action']) && $_POST['action'] === 'cek_bentrok_edit') {
    header('Content-Type: application/json');

    $id = (int)($_POST['id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $tanggalSelesai = $_POST['tanggal_selesai'] ?? $tanggal;
    $mulai   = $_POST['waktu_mulai'] ?? '';
    $selesai = $_POST['waktu_selesai'] ?? '';
    if (strtotime($tanggalSelesai) < strtotime($tanggal)) {
      echo json_encode([
        'success' => false,
        'message' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.'
      ]);
      exit;
    }
    if (!$id || $tanggal === '' || $mulai === '' || $selesai === '') {
      echo json_encode([
        'success' => false,
        'message' => 'Tanggal, waktu mulai, dan waktu selesai wajib diisi.'
      ]);
      exit;
    }

    if (strtotime($selesai) <= strtotime($mulai)) {
      echo json_encode([
        'success' => false,
        'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
      ]);
      exit;
    }

    $qAgenda = $conn->prepare("
      SELECT id_ruang_rapat, id_pertemuan
      FROM tb_agenda_rapat
      WHERE id = ?
      LIMIT 1
    ");
    $qAgenda->bind_param("i", $id);
    $qAgenda->execute();
    $agenda = $qAgenda->get_result()->fetch_assoc();

    if (!$agenda) {
      echo json_encode([
        'success' => false,
        'message' => 'Data agenda tidak ditemukan.'
      ]);
      exit;
    }
    if ((int)$agenda['id_pertemuan'] === 1) {
      $tanggalSelesai = $tanggal;
    }

    $ruangAgenda = array_filter(array_map('trim', explode(',', $agenda['id_ruang_rapat'])));

      $startDate = new DateTime($tanggal);
      $endDate   = new DateTime($tanggalSelesai);

      while ($startDate <= $endDate) {
        $tanggalCek = $startDate->format('Y-m-d');

        foreach ($ruangAgenda as $idRuang) {
          $cek = $conn->prepare("
            SELECT topik_rapat, nama_ruang, tanggal, tanggal_selesai, waktu_mulai, waktu_selesai
            FROM tb_agenda_rapat
            WHERE status = 1
            AND id != ?
            AND ? BETWEEN tanggal AND COALESCE(NULLIF(tanggal_selesai, '0000-00-00'), tanggal)
            AND FIND_IN_SET(?, REPLACE(id_ruang_rapat, ' ', ''))
            AND NOT (
              waktu_selesai <= ?
              OR waktu_mulai >= ?
            )
            LIMIT 1
          ");

          $cek->bind_param(
            "issss",
            $id,
            $tanggalCek,
            $idRuang,
            $mulai,
            $selesai
          );

          $cek->execute();
          $bentrok = $cek->get_result()->fetch_assoc();

          if ($bentrok) {
            echo json_encode([
              'success' => false,
              'message' => 'Jadwal bentrok pada ' . formatTanggalIndo($tanggalCek) . ' dengan agenda: ' . $bentrok['topik_rapat']
            ]);
            exit;
          }
        }

        $startDate->modify('+1 day');
      }
    echo json_encode(['success' => true]);
    exit;
  }
  if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int) $_POST['id'];
    if ($roleUser === 'user') {
      $upload = uploadDokumen('file_upload');
      if ($upload) {
        $stmt = $conn->prepare("
          UPDATE tb_agenda_rapat
          SET file_upload = ?
          WHERE id = ?
          AND id_pertemuan = 1
        ");
        $stmt->bind_param("si", $upload, $id);
        $stmt->execute();
      }
      echo json_encode(['success' => true]);
      exit;
    }
    
    if ($roleUser === 'admin' || $roleUser === 'staf khusus' || $roleUser === 'staf umum') {
      $cekAkses = $conn->prepare("
        SELECT id_pertemuan, penyelenggara
        FROM tb_agenda_rapat
        WHERE id = ?
        LIMIT 1
      ");
      $cekAkses->bind_param("i", $id);
      $cekAkses->execute();
      $dataAkses = $cekAkses->get_result()->fetch_assoc();

      if (!$dataAkses) {
        echo json_encode([
          'success' => false,
          'message' => 'Data tidak ditemukan.'
        ]);
        exit;
      }

      if ($roleUser === 'staf khusus') {
        $qUnit = $conn->prepare("
          SELECT UNIT_II
          FROM tb_pegawai
          WHERE nip_pegawai = ?
          LIMIT 1
        ");
        $qUnit->bind_param("s", $nipUser);
        $qUnit->execute();
        $unitUser = $qUnit->get_result()->fetch_assoc()['UNIT_II'] ?? '';

        if (strtolower(trim($dataAkses['penyelenggara'])) !== strtolower(trim($unitUser))) {
          echo json_encode([
            'success' => false,
            'message' => 'Anda hanya dapat mengakses agenda sesuai unit penyelenggara.'
          ]);
          exit;
        }

        // Jika agenda rapat, staf khusus HANYA boleh upload dokumen
        if ((int)$dataAkses['id_pertemuan'] === 1) {
          $upload = uploadDokumen('file_upload');

          if ($upload) {
            $stmt = $conn->prepare("
              UPDATE tb_agenda_rapat
              SET file_upload = ?
              WHERE id = ?
            ");
            $stmt->bind_param("si", $upload, $id);
            $stmt->execute();
          }

          echo json_encode(['success' => true]);
          exit;
        }
      }

      $tanggal     = $_POST['tanggal'] ?? '';
      $tanggal_selesai = $_POST['tanggal_selesai'] ?? $tanggal;

      if ((int)$dataAkses['id_pertemuan'] === 1) {
        $tanggal_selesai = $tanggal;
      }
      $mulai       = $_POST['waktu_mulai'] ?? '';
      $selesai     = $_POST['waktu_selesai'] ?? '';
      $link        = $_POST['link_zoom'] ?? '';
      $topik_rapat = $_POST['topik_rapat'] ?? '';
      $kehadiran   = $_POST['kehadiran'] ?? '';
      $keterangan  = $_POST['keterangan'] ?? '';
      $jenis_kepentingan = $_POST['jenis_kepentingan'] ?? '';

      if ((int)$dataAkses['id_pertemuan'] === 1) {
        $jenis_kepentingan = '0';
      }

      if ((int)$dataAkses['id_pertemuan'] === 2 && !in_array($jenis_kepentingan, ['0', '1'], true)) {
        echo json_encode([
          'success' => false,
          'message' => 'Pilihan penayangan TV Wall wajib dipilih.'
        ]);
        exit;
      }
      $pendampingArr = $_POST['pendamping'] ?? [];
      $pelaksanaArr  = $_POST['nama_pelaksana'] ?? [];

      if (!is_array($pendampingArr)) { $pendampingArr = [$pendampingArr];}
      if (!is_array($pelaksanaArr)) { $pelaksanaArr = [$pelaksanaArr]; }

      $pendamping = implode(',', array_filter(array_map('trim', $pendampingArr)));
      $nama_pelaksana = implode(',', array_filter(array_map('trim', $pelaksanaArr)));

      if ($kehadiran !== 'Hadir' && $kehadiran !== 'Diwakilkan') {
        $pendamping = '';
        $nama_pelaksana = '';
      }
      $conn->begin_transaction();

      try {
        $upload = uploadDokumen('file_upload');
        if ($upload) {
          $stmt = $conn->prepare("
            UPDATE tb_agenda_rapat
            SET tanggal = ?,
                tanggal_selesai = ?,
                waktu_mulai = ?,
                waktu_selesai = ?,
                link_zoom = ?,
                file_upload = ?,
                topik_rapat = ?,
                pendamping = ?,
                jenis_kepentingan = ?,
                kehadiran = ?,
                keterangan = ?,
                nama_pelaksana = ?
            WHERE id = ?
          ");

          $stmt->bind_param(
            "ssssssssssssi",
            $tanggal,
            $tanggal_selesai,
            $mulai,
            $selesai,
            $link,
            $upload,
            $topik_rapat,
            $pendamping,
            $jenis_kepentingan,
            $kehadiran,
            $keterangan,
            $nama_pelaksana,
            $id
          );

        } else {
          $stmt = $conn->prepare("
            UPDATE tb_agenda_rapat
            SET tanggal = ?,
                tanggal_selesai = ?,
                waktu_mulai = ?,
                waktu_selesai = ?,
                link_zoom = ?,
                topik_rapat = ?,
                pendamping = ?,
                jenis_kepentingan = ?,
                kehadiran = ?,
                keterangan = ?,
                nama_pelaksana = ?
            WHERE id = ?
          ");

          $stmt->bind_param(
            "sssssssssssi",
            $tanggal,
            $tanggal_selesai,
            $mulai,
            $selesai,
            $link,
            $topik_rapat,
            $pendamping,
            $jenis_kepentingan,
            $kehadiran,
            $keterangan,
            $nama_pelaksana,
            $id
          );
        }

        $stmt->execute();

        $stmtBmn = $conn->prepare("
          UPDATE pengajuan_bmn
          SET tgl_pinjam = ?,
              tgl_kembali = ?,
              lama_hari = 0
          WHERE id_agenda = ?
        ");
        $stmtBmn->bind_param("ssi", $tanggal, $tanggal_selesai, $id);
        $stmtBmn->execute();
        $conn->commit();
        echo json_encode(['success' => true]);

      } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
          'success' => false,
          'message' => $e->getMessage()
        ]);
      }
      exit;
    }
  }

  if (isset($_POST['action']) && $_POST['action'] === 'reject') {
    $id = (int) $_POST['id'];
    if ($roleUser !== 'admin' && $roleUser !== 'staf khusus' && $roleUser !== 'staf umum') {
      echo json_encode([
        'success' => false,
        'message' => 'Anda tidak memiliki akses'
      ]);
      exit;
    }

    if ($roleUser === 'staf khusus') {
      $cek = $conn->prepare("
        SELECT rp.id_pertemuan, rp.penyelenggara, p.UNIT_II
        FROM tb_agenda_rapat rp
        LEFT JOIN tb_pegawai p ON p.nip_pegawai = ?
        WHERE rp.id = ?
        LIMIT 1
      ");
      $cek->bind_param("si", $nipUser, $id);
      $cek->execute();
      $akses = $cek->get_result()->fetch_assoc();

      if (
        !$akses ||
        $akses['id_pertemuan'] != 2 ||
        strtolower(trim($akses['penyelenggara'])) !== strtolower(trim($akses['UNIT_II']))
      ) {
        echo json_encode([
          'success' => false,
          'message' => 'Anda hanya dapat menghapus agenda kegiatan sesuai penyelenggara.'
        ]);
        exit;
      }
    }
    $conn->begin_transaction();

    try {
      $stmt = $conn->prepare("
        UPDATE tb_agenda_rapat
        SET status = 0
        WHERE id = ?
      ");
      $stmt->bind_param("i", $id);
      $stmt->execute();

      $stmt2 = $conn->prepare("
        UPDATE pengajuan_bmn
        SET status = 'Batal'
        WHERE id_agenda = ?
        AND status != 'Selesai'
      ");
      $stmt2->bind_param("i", $id);
      $stmt2->execute();
      $conn->commit();
      echo json_encode(['success' => true]);

    } catch (Exception $e) {
      $conn->rollback();
      echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
      ]);
    }
    exit;
  }

  $totalQ = $conn->query("
    SELECT COUNT(DISTINCT rp.id) AS total
    FROM tb_agenda_rapat rp
    LEFT JOIN tb_ruangan_rapat r
      ON FIND_IN_SET(r.id_ruang, rp.id_ruang_rapat)
    $where
  ");

  $totalData  = $totalQ->fetch_assoc()['total'];
  $totalPages = ceil($totalData / $limit);
  $queryParams = "tahun=" . $tahun;

  if ($bulan !== '') { $queryParams .= "&bulan=" . $bulan; }
  if ($q !== '') { $queryParams .= "&q=" . urlencode($q); }
  $sql = "
    SELECT
      rp.*,
      rp.nama_ruang,
      GROUP_CONCAT(DISTINCT b.spek_bmn ORDER BY b.spek_bmn SEPARATOR ', ') AS fasilitas_nama,
      (SELECT nama FROM tb_user WHERE nip = rp.nip_pemohon LIMIT 1) AS nama,
      (SELECT nama_pegawai FROM tb_pegawai WHERE nip_pegawai = rp.nip_pemohon LIMIT 1) AS nama_pegawai,
      (
        SELECT GROUP_CONCAT(p.nama_pegawai SEPARATOR ', ')
        FROM tb_pegawai p
        WHERE FIND_IN_SET(p.nip_pegawai, REPLACE(rp.nama_pelaksana, ' ', ''))
      ) AS nama_pelaksana_text,
      (
        SELECT GROUP_CONCAT(p.nama_pegawai SEPARATOR ', ')
        FROM tb_pegawai p
        WHERE FIND_IN_SET(p.nip_pegawai, REPLACE(rp.pendamping, ' ', ''))
      ) AS pendamping_text
    FROM tb_agenda_rapat rp
    LEFT JOIN tb_ruangan_rapat r
      ON FIND_IN_SET(r.id_ruang, rp.id_ruang_rapat)
    LEFT JOIN pengajuan_bmn pb
      ON pb.id_agenda = rp.id
    LEFT JOIN pengajuan_bmn_detail pbd
      ON pbd.id_pengajuan = pb.id_pengajuan
    LEFT JOIN daftar_bmn b
      ON b.id_bmn = pbd.id_bmn

    $where

    GROUP BY rp.id
    ORDER BY rp.time_stamp DESC, rp.tanggal DESC, rp.waktu_mulai DESC
    LIMIT $limit OFFSET $offset
  ";

  $data = $conn->query($sql);
  $years = [];

  $yq = $conn->query("
    SELECT DISTINCT YEAR(tanggal) tahun
    FROM tb_agenda_rapat
    ORDER BY tahun DESC
  ");

  while ($y = $yq->fetch_assoc()) {
    $years[] = $y['tahun'];
  }

  $bulanList = [ 
    1  => "Januari", 2  => "Februari", 3  => "Maret", 4  => "April", 5  => "Mei", 6  => "Juni",
    7  => "Juli", 8  => "Agustus", 9  => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
  ];
  function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) {
      return '-';
    }

    $bulan = [ 
      1  => "Januari", 2  => "Februari", 3  => "Maret", 4  => "April", 5  => "Mei", 6  => "Juni",
      7  => "Juli", 8  => "Agustus", 9  => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
    ];

    $date = DateTime::createFromFormat('Y-m-d', $tanggal);

    if (!$date) {
      return '-';
    }

    return $date->format('d') . ' ' . $bulan[(int)$date->format('m')] . ' ' . $date->format('Y');
  }

  $pegawaiList = [];
  $qPegawai = $conn->query("
    SELECT nip_pegawai, nama_pegawai
    FROM tb_pegawai
    WHERE nama_pegawai IS NOT NULL
    AND nama_pegawai != ''
    AND kd_satker = '694762'
    ORDER BY nama_pegawai ASC
  ");

  while ($row = $qPegawai->fetch_assoc()) {
    $pegawaiList[] = $row;
  }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Pengajuan Rapat</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
  <style>
    :root{
      --primary: #1e40af;
      --primary-hover: #1d4ed8;
      --soft-blue: #eff6ff;
      --text-dark: #0f172a;
      --text: #334155;
      --muted: #64748b;
      --border: #e2e8f0;
      --bg: #f8fafc;
      --danger: #dc2626;
      --success:  #047857;
    }

    body{
      font-family:'Inter',sans-serif;
      background:var(--bg);
      color:var(--text);
    }

    .main-container{
      width:95%;
      max-width:1800px;
      margin:18px auto 44px;
    }

    /* Card & Header */
    .card{
      border:1px solid var(--border)!important;
      border-radius:20px;
      box-shadow:0 6px 20px rgba(15,23,42,.04);
    }

    .card:hover{
      box-shadow:0 10px 25px rgba(0,0,0,.06);
    }

    .page-header-modern{
      background:linear-gradient(135deg,#fff 0%,#f8fbff 100%);
      border:1px solid var(--border);
      border-radius:24px;
      padding:24px 28px;
      box-shadow:0 8px 30px rgba(15,23,42,.04);
      overflow:hidden;
    }

    .header-icon{
      width:68px;
      height:68px;
      border-radius:20px;
      background:var(--primary);
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:28px;
      box-shadow:0 10px 25px rgba(37,99,235,.25);
      flex-shrink:0;
    }

    .small-badge{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 12px;
      border-radius:999px;
      background:var(--soft-blue);
      color:var(--primary);
      font-size:.7rem;
      font-weight:600;
      margin-bottom:10px;
      letter-spacing:.04em;
    }

    .agenda-title{
      font-size:1.6rem;
      font-weight:800;
      line-height:1.1;
      color:var(--text-dark);
      letter-spacing:-.03em;
      margin:0;
    }

    .agenda-subtitle{
      color:var(--muted);
      font-size:0.8rem;
      font-weight:500;
    }

    /* Form & Button */
    .form-control,
    .form-select,
    .custom-input{
      border:1.5px solid var(--border);
      border-radius:10px;
      padding:.6rem 1rem;
      font-size:1rem;
      transition:.2s ease;
    }

    .form-control:focus,
    .form-select:focus,
    .custom-input:focus{
      border-color: var(--primary);
      box-shadow:0 0 0 4px rgba(59,130,246,.1);
      outline:none;
    }

    .input-group-text{
      border-top-left-radius:var(--bs-border-radius)!important;
      border-bottom-left-radius:var(--bs-border-radius)!important;
    }

    .btn{
      transition:.2s ease;
    }

    .btn:hover{
      transform:translateY(-1px);
    }

    .btn-primary,
    .btn-add{
      background:var(--primary);
      border:0;
      border-radius:10px;
      font-weight:600;
    }

    .btn-primary:hover,
    .btn-add:hover{
      background:var(--primary-hover);
    }

    .btn-add{
      padding:10px 20px;
      box-shadow:0 4px 12px rgba(37,99,235,.2);
    }

    .btn-outline-secondary{
      border-radius:10px;
    }

    /* Dropdown */
    .dropdown-menu{
      border:1px solid #e9ecef;
      border-radius:14px;
      box-shadow:0 .5rem 1rem rgba(0,0,0,.08);
      font-size:.85rem;
    }

    .dropdown-item{
      white-space:normal;
    }

    /* Table */
    .table-responsive{
      overflow-x:auto;
      -webkit-overflow-scrolling:touch;
    }

    .custom-table{
      width:100%;
      min-width:1000px;
      border-collapse:separate;
      border-spacing:0;
      border-radius:16px;
      overflow:hidden;
      border:1px solid var(--border);
      background:#fff;
      table-layout: fixed;
    }

    .custom-table thead th{
      background:#f8fafc;
      font-size:14px;
      text-transform:uppercase;
      letter-spacing:.08em;
      color:var(--muted);
      padding:14px;
      text-align:center;
      white-space:nowrap;
      vertical-align:middle;
    }

    .custom-table td{
      padding:14px;
      font-size:12px;
      border-top:1px solid #f1f5f9;
      vertical-align:middle;
    }

    .custom-table tbody tr:nth-child(even){
      background:#f8fafc;
    }

    .custom-table tbody tr:hover{
      background:#eff6ff;
    }

    .custom-table th:nth-child(1),
    .custom-table td:nth-child(1){
      width:5%;
    }

    .custom-table th:nth-child(2),
    .custom-table td:nth-child(2){
      width:24%;
      white-space:normal;
    }

    .custom-table th:nth-child(3),
    .custom-table td:nth-child(3){
      width:18%;
      white-space:normal;
    }

    .custom-table th:nth-child(4),
    .custom-table td:nth-child(4){
      width:14%;
      white-space:normal;
    }

    .custom-table th:nth-child(5),
    .custom-table td:nth-child(5){
      width:13%;
      white-space:normal;
    }

    .custom-table th:nth-child(6),
    .custom-table td:nth-child(6){
      width:10%;
      white-space:nowrap;
    }

    /* Agenda Column */
    .agenda-card-title{
      font-size:1rem;
      font-weight:700;
      color:var(--text-dark);
      line-height:1.4;
      text-align:justify;
      text-justify:inter-word;
    }

    .agenda-meta{
      display:flex;
      align-items:center;
      gap:6px;
      font-size:.8rem;
      color:var(--muted);
      margin-top:6px;
    }

    /* Schedule */
    .schedule-card,
    .info-stack{
      display:flex;
      flex-direction:column;
      gap:8px;
    }

    .schedule-main,
    .info-row,
    .requester-card{
      display:flex;
      align-items:center;
      gap:10px;
    }

    .schedule-place,
    .requester-name{
      font-size:.85rem;
      font-weight:700;
      color:var(--text-dark);
      line-height:1.35;
    }
    .requester-name{
      font-size:.8rem;
    }

    .schedule-time,
    .requester-time{
      font-size:.78rem;
      color:var(--muted);
      font-weight:600;
    }
    .requester-time{
      font-size:.7rem;
    }

    .schedule-date,
    .badge-meeting,
    .badge-agenda{
      display:inline-flex;
      align-items:center;
      gap:6px;
      width:fit-content;
      border-radius:999px;
      font-weight:700;
    }

    .schedule-date{
      padding:5px 10px;
      background:var(--soft-blue);
      color:#1d4ed8;
      font-size:.75rem;
    }

    .badge-meeting{
      padding:5px 9px;
      background:#f8fafc;
      color:#475569;
      border:1px solid var(--border);
      font-size:.7rem;
      font-weight:600;
    }

    .badge-agenda{
      padding:7px 11px;
      font-size:.75rem;
    }

    .badge-rapat{
      background:var(--soft-blue);
      color:var(--primary);
    }

    .badge-kegiatan{
      background:#ecfdf5;
      color:var(--success);
    }

    .info-row{
      align-items:flex-start;
      font-size:.75rem;
      color:#475569;
    }

    .info-row strong{
      color:var(--text-dark);
      font-size:.8rem;
      font-weight:600;
    }

    .icon-soft{
      width:28px;
      height:28px;
      border-radius:9px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:var(--soft-blue);
      color:var(--primary);
      flex-shrink:0;
    }

    .icon-soft.red{
      background:#fef2f2;
      color:var(--danger);
    }

    /* Requester */
    .requester-avatar{
      width:38px;
      height:38px;
      border-radius:50%;
      background:#ecfdf5;
      color:var(--success);
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:800;
      font-size:.85rem;
      flex-shrink:0;
    }

    /* Action Button */
    .action-wrapper{
      display:flex;
      justify-content:center;
      gap:5px;
    }

    .btn-action{
      width:42px;
      height:42px;
      border-radius:10px;
      border:0;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      transition:.25s ease;
    }

    .btn-edit-modern{
      background:var(--soft-blue);
      color:var(--primary);
    }

    .btn-edit-modern:hover{
      background:var(--primary);
      color:#fff;
      transform:translateY(-2px);
      box-shadow:0 8px 18px rgba(37,99,235,.2);
    }

    .btn-edit-modern.btn-edit{
      background:#ecfdf5!important;
      color:var(--success)!important;
      box-shadow:none!important;
    }

    .btn-edit-modern.btn-edit:hover,
    .btn-edit-modern.btn-edit:focus,
    .btn-edit-modern.btn-edit:active{
      background:var(--success)!important;
      color:#fff!important;
      box-shadow:0 8px 18px rgba(4,120,87,.18)!important;
    }

    .btn-delete-modern{
      background:#fef2f2;
      color:var(--danger);
    }

    .btn-delete-modern:hover{
      background:var(--danger);
      color:#fff;
      transform:translateY(-2px);
      box-shadow:0 8px 18px rgba(220,38,38,.2);
    }

    /* Pagination */
    .pagination .page-link{
      min-width:40px;
      text-align:center;
      border-radius:10px;
      color:#334155;
      border:1px solid var(--border);
      padding:.45rem .75rem;
      transition:.2s ease;
    }

    .pagination .page-link:hover{
      background:var(--soft-blue);
      color:#2563eb;
      border-color:#bfdbfe;
    }

    .pagination .page-item.active .page-link{
      background:var(--primary);
      border-color:var(--primary);
      color:#fff;
      box-shadow:0 4px 10px rgba(59,130,246,.18);
    }

    .pagination .page-item.disabled .page-link{
      background:#f8fafc;
      color:#94a3b8;
    }

    /* Modal */
    .modal-content{
      border:0;
      border-radius:18px;
      overflow:hidden;
    }

    .modal-modern .modal-content{
      border-radius:24px;
      background:#f8fafc;
      box-shadow:0 20px 60px rgba(15,23,42,.18);
    }

    .modal-modern .modal-header{
      background:linear-gradient(135deg,#fff 0%,#f8fbff 100%);
      border-bottom:1px solid var(--border);
      padding:24px 28px 18px;
    }

    .modal-modern .modal-title{
      font-size:1.3rem;
      font-weight:800;
      color:var(--text-dark);
      letter-spacing:-.02em;
    }

    .modal-modern .modal-body{
      padding:24px;
      max-height:75vh;
      overflow-y:auto;
    }

    .modal-modern .modal-footer{
      background:#fff;
      border-top:1px solid var(--border);
      padding:18px 24px;
    }

    .modal-modern .form-label{
      font-size:.9rem;
      font-weight:600;
      color:#334155;
      margin-bottom:6px;
    }

    .modal-modern .form-control,
    .modal-modern .form-select{
      min-height:48px;
      border-radius:14px;
      padding:.7rem 1rem;
      font-size:.95rem;
    }

    .modal-modern textarea.form-control{
      min-height:auto;
    }

    .edit-section{
      background:#fff;
      border:1px solid var(--border);
      border-radius:20px;
      padding:18px;
      margin-bottom:18px;
      box-shadow:0 4px 14px rgba(15,23,42,.03);
    }

    .edit-section:hover{
      border-color: #bfdbfe;
    }

    .edit-section-title{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:18px;
    }

    .edit-section-icon,
    .zoom-info-icon{
      width:42px;
      height:42px;
      border-radius:14px;
      background:var(--soft-blue);
      color:#2563eb;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:18px;
      flex-shrink:0;
    }

    .edit-section-title h6{
      margin:0;
      font-size:1rem;
      font-weight:700;
      color:var(--text-dark);
    }

    .edit-section-subtitle,
    .zoom-info-subtitle{
      font-size:.85rem;
      color:var(--muted);
      margin-top:2px;
    }

    .readonly-field,
    .modal-modern .field-readonly{
      background:#e2e8f0!important;
      border-color:#cbd5e1!important;
      color:#334155!important;
      cursor:not-allowed;
      font-weight:500;
    }

    .readonly-clickable{
      cursor:pointer!important;
      background:var(--soft-blue)!important;
      border-color:#93c5fd!important;
      color:#1d4ed8!important;
      font-weight:600;
    }

    .readonly-clickable:hover{
      background:#dbeafe!important;
      color:#1e40af!important;
    }

    .no-resize{
      resize:none;
    }

    /* Zoom Box */
    .zoom-info-card,
    #zoomInfoWrapper{
      background:#fff;
      border:1px solid #dbeafe;
      border-radius:20px;
      padding:22px;
      margin-bottom:18px;
      box-shadow:0 4px 14px rgba(37,99,235,.05);
    }

    .zoom-info-header{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:8px;
    }

    .zoom-info-title{
      font-size:1rem;
      font-weight:700;
      color:var(--text-dark);
    }

    .zoom-label{
      font-size:.82rem;
      font-weight:700;
      color:#475569;
      margin-bottom:6px;
    }

    .input-action-btn{
      border-radius:12px;
      font-weight:600;
      white-space:nowrap;
    }

    /* Info Box */
    .info-box-soft{
      background:#f8fafc;
      border:1px solid var(--border);
      border-radius:16px;
      padding:16px;
    }

    .info-box-soft-title{
      font-size:.85rem;
      font-weight:700;
      color:#475569;
      margin-bottom:8px;
      text-transform:uppercase;
      letter-spacing:.04em;
    }

    /* Upload */
    .custom-upload-group{
      border:1.5px solid var(--border);
      border-radius:12px;
      overflow:hidden;
      box-shadow:0 2px 5px rgba(0,0,0,.02);
    }

    .custom-upload-group:hover{
      border-color:#3b82f6;
    }

    .custom-upload-group .form-control,
    .custom-upload-group .input-group-text{
      border:0;
      background:#fff;
      font-size:1rem;
    }

    .custom-upload-group .btn-outline-primary{
      border:0;
      border-left:1.5px solid var(--border);
      border-radius:0;
      background:#f8faff;
      font-size:.9rem;
      letter-spacing:.5px;
    }

    .custom-upload-group .btn-outline-primary:hover{
      background:var(--primary);
      color:#fff;
    }
    .custom-upload-group .btn-outline-primary {
    background: var(--primary) !important;
    color: #fff !important;
    border-left: 1.5px solid var(--primary) !important;
    }
    .custom-upload-group .btn-outline-primary:hover,
    .custom-upload-group .btn-outline-primary:focus,
    .custom-upload-group .btn-outline-primary:active {
    background: var(--primary-hover) !important;
    color: #fff !important;
    transform: translateY(-1px);
    }

    .file-preview-modal .modal-content{
      border:0;
      border-radius:20px;
      overflow:hidden;
    }

    .file-preview-box{
      background:#f8fafc;
      border:1px solid var(--border);
      border-radius:16px;
      min-height:420px;
      overflow:hidden;
    }

    .preview-img{
      width:100%;
      max-height:70vh;
      object-fit:contain;
      background:#fff;
    }

    .preview-iframe{
      width:100%;
      height:70vh;
      border:0;
      background:#fff;
    }

    /* Delete Modal */
    .delete-modal{
      border-radius:16px;
      border:0;
      box-shadow:0 20px 40px rgba(0,0,0,.1);
    }

    .icon-wrapper{
      width:70px;
      height:70px;
      margin:0 auto;
      border-radius:50%;
      background:rgba(239,68,68,.1);
      display:flex;
      align-items:center;
      justify-content:center;
    }

    .icon-wrapper i{
      font-size:28px;
      color:#ef4444;
    }

    /* Select2 Edit */
    .pegawai-select-edit + .select2-container{
      width:100%!important;
    }

    .pegawai-select-edit + .select2-container .select2-selection--multiple{
      min-height:48px!important;
      border:1.5px solid var(--border)!important;
      border-radius:14px!important;
      padding:4px 10px!important;
      display:flex!important;
      align-items:center!important;
    }
    .pegawai-select-edit + .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    .pegawai-select-edit + .select2-container.select2-container--focus .select2-selection--multiple,
    .pegawai-select-edit + .select2-container.select2-container--open .select2-selection--multiple {
      border-color: var(--primary) !important;
      box-shadow: 0 0 0 4px rgba(59,130,246,.1) !important;
      outline: none !important;
    }
    /* Kotak dropdown list Select2 nama pelaksana & pendamping */
    .select2-container--open .select2-dropdown{
      border:1.5px solid var(--border) !important;
      border-radius:14px !important;
      box-shadow:0 .5rem 1rem rgba(0,0,0,.08) !important;
      overflow:hidden !important;
      font-size:.95rem;
      background:#fff;
    }

    /* Isi list nama */
    .select2-results__option{
      padding:10px 14px !important;
      font-size:.95rem !important;
      color:#334155;
    }

    /* Hover list nama */
    .select2-results__option--highlighted{
      background:var(--soft-blue) !important;
      color:var(--primary) !important;
    }

    /* Search input di dalam dropdown */
    .select2-search--dropdown{
      padding:8px !important;
    }

    .select2-search--dropdown .select2-search__field{
      border:1.5px solid var(--border) !important;
      border-radius:10px !important;
      padding:.6rem 1rem !important;
      outline:none !important;
    }

    .select2-search--dropdown .select2-search__field:focus{
      border-color:var(--primary) !important;
      box-shadow:0 0 0 4px rgba(59,130,246,.1) !important;
    }

    .pegawai-select-edit + .select2-container .select2-search--inline .select2-search__field{
      margin-top:0!important;
      height:30px!important;
      line-height:30px!important;
      padding:0!important;
    }

    .pegawai-select-edit + .select2-container .select2-selection__choice{
      position:relative!important;
      background:var(--soft-blue)!important;
      border:1px solid #bfdbfe!important;
      color:var(--primary)!important;
      border-radius:999px!important;
      padding:5px 10px 5px 28px!important;
      font-size:.9rem;
    }

    .pegawai-select-edit + .select2-container .select2-selection__choice__remove{
      position:absolute!important;
      left:8px!important;
      border:0!important;
      color:var(--primary)!important;
    }

    /* Error */
    .field-error{
      color:var(--danger);
      font-size:.82rem;
      margin-top:6px;
      font-weight:500;
    }

    .is-invalid,
    .is-field-invalid{
      border-color:var(--danger)!important;
      box-shadow:0 0 0 4px rgba(220,38,38,.08)!important;
    }

    .btn-save-modern{
      background: var(--primary);
      color: #fff !important;
      border: 0;
      border-radius: 12px;
      padding: 12px 22px;
      font-weight: 600;
      box-shadow: 0 8px 18px rgba(37,99,235,.18);
    }

    .btn-save-modern:hover,
    .btn-save-modern:focus,
    .btn-save-modern:active{
      background: var(--primary-hover) !important;
      color: #fff !important;
      border: 0 !important;
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(37,99,235,.25);
    }

    .btn-cancel-modern{
      border-radius:12px;
      padding:12px 22px;
    }
    .field-error{
      color: var(--danger);
      font-size: .82rem;
      margin-top: 6px;
      font-weight: 500;
    }

    .is-field-invalid{
      border-color: var(--danger)!important;
      box-shadow: 0 0 0 4px rgba(220,38,38,.08)!important;
    }

    .edit-tvwall-box{
      background:#f8fafc;
      border:1.5px solid var(--border);
      border-radius:16px;
      padding:16px;
    }

    .edit-tvwall-option-box{
      padding:12px 14px;
      border-radius:12px;
      background:#fff;
      border:1.5px solid var(--border);
    }

    .edit-tvwall-option-box:hover{
      border-color:#bfdbfe;
    }

    .edit-tvwall-option:checked{
      background-color:var(--primary);
      border-color:var(--primary);
    }

  </style>
</head>
<body>
  <div class="container main-container">
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-body">
        <div class="page-header-modern mb-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="bi bi-calendar-event"></i>
              </div>
            <div>
            <div class="small-badge"> Sistem Agenda Internal </div>
            <h1 class="agenda-title mb-1"> Agenda Kegiatan </h1>
            <p class="agenda-subtitle mb-0"> Deputi Bidang Usaha Menengah </p>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <a href="form_pengajuan_agenda.php" class="btn btn-primary btn-add">
            <i class="bi bi-plus-lg me-2"></i> Tambah Agenda
          </a>
          <a href="export_excel_rapat.php?<?= $queryParams ?>" class="btn btn-primary btn-add">
            <i class="bi bi-download me-2"></i> Export
          </a>
        </div>
      </div>
    </div>
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-white">
            <i class="fas fa-search text-muted"></i>
          </span>
          <input type="text" name="q" 
            value="<?= htmlspecialchars($q) ?>"
            class="form-control border-start-0 custom-input"
            placeholder="Pencarian">
        </div>
      </div>
      <div class="col-md-2">
        <div class="dropdown w-100">
          <button class="form-select text-start text-muted custom-input" type="button" id="dropdownBulan" data-bs-toggle="dropdown" aria-expanded="false">
              <?= $bulan ? $bulanList[$bulan] : 'Bulan' ?>
          </button>
          <ul class="dropdown-menu w-100" id="bulan_menu">
              <li><a class="dropdown-item bulan-option" data-value="" data-text="Bulan">Bulan</a></li>
              <?php foreach ($bulanList as $key => $val): ?>
                  <li>
                      <a class="dropdown-item bulan-option" data-value="<?= $key ?>" data-text="<?= $val ?>">
                          <?= $val ?>
                      </a>
                  </li>
              <?php endforeach; ?>
          </ul>
        </div>
        <input type="hidden" name="bulan" id="bulanSelect" value="<?= $bulan ?>">
      </div>
      <div class="col-md-2">
        <div class="dropdown w-100">
          <button class="form-select text-start text-muted custom-input" type="button" id="dropdownTahun" data-bs-toggle="dropdown" aria-expanded="false">
            <?= $tahun ? $tahun : 'Tahun' ?>
          </button>
          <ul class="dropdown-menu w-100" id="tahun_menu">
            <li><a class="dropdown-item tahun-option" data-value="" data-text="Tahun">Tahun</a></li>
            <?php foreach ($years as $y): ?>
                <li>
                    <a class="dropdown-item tahun-option" data-value="<?= $y ?>" data-text="<?= $y ?>">
                        <?= $y ?>
                    </a>
                </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <input type="hidden" name="tahun" id="tahunSelect" value="<?= $tahun ?>">
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary custom-input">
          <i class="fas fa-filter me-1"></i> Filter
        </button>
      </div>
      <div class="col-md-2 d-grid">
        <a href="?" class="btn btn-outline-secondary custom-input">Reset</a>
      </div>
    </form>
  </div>
  <div class="table-responsive px-3 pb-2">
    <table class="custom-table" id="rapatTable">
      <thead>
        <tr>
          <th >No.</th>
          <th style=" max-width: 360px;">Agenda</th>
          <th>Lokasi dan Waktu</th>
          <th >Kategori</th>
          <th >Pemohon</th>
          <th >Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php $no=($page-1)*$limit+1; while($r=$data->fetch_assoc()): ?>
          <tr>
            <td class="text-center fw-semibold text-muted"><?= $no++ ?></td>
            <td style="min-width:320px">
              <div class="agenda-card-title">
                <?= htmlspecialchars($r['topik_rapat']) ?>
              </div>
              <div class="agenda-meta">
                <i class="bi bi-building"></i>
                <span><?= htmlspecialchars($r['penyelenggara'] ?? '-') ?></span>
              </div>
            </td>
            <td>
              <div class="schedule-card">
                <div class="schedule-main">
                  <div>
                    <div class="schedule-place">
                      <?= htmlspecialchars($r['nama_ruang'] ?? '-') ?>
                    </div>
                    <div class="schedule-time">
                      <?= substr($r['waktu_mulai'], 0, 5) ?> - <?= substr($r['waktu_selesai'], 0, 5) ?> WIB
                    </div>
                  </div>
                </div>
                <div class="schedule-date">
                  <i class="bi bi-calendar2"></i>
                  <?php
                    $tglMulai = $r['tanggal'];
                    $tglSelesai = !empty($r['tanggal_selesai']) ? $r['tanggal_selesai'] : $r['tanggal'];

                    if ($tglMulai === $tglSelesai) {
                      echo formatTanggalIndo($tglMulai);
                    } else {
                      echo formatTanggalIndo($tglMulai) . ' - ' . formatTanggalIndo($tglSelesai);
                    }
                  ?>
                </div>
                <?php
                  $meetingType = strtolower(trim($r['meeting_type'] ?? ''));
                  $linkMeeting = $r['link_zoom'] ?? $r['online_link'] ?? '';
                  $showLinkMeeting = in_array($meetingType, [
                    'online',
                    'hybrid',
                    'hybrid luar kantor'
                  ], true);
                ?>
                <?php if ($showLinkMeeting && !empty($linkMeeting)): ?>
                  <a href="<?= htmlspecialchars($linkMeeting) ?>" 
                    target="_blank" 
                    class="badge-meeting text-decoration-none mt-1">
                    <i class="bi bi-camera-video-fill"></i>
                    Link Meeting
                  </a>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php
                $jenisAgenda = ((int)$r['id_pertemuan'] === 1) ? 'Agenda Rapat' : 'Agenda Kegiatan';
                $kategoriAgenda = $r['kategori_rapat'] ?? $r['kategori_agenda'] ?? '-';
                $badgeAgenda = ((int)$r['id_pertemuan'] === 1) ? 'badge-rapat' : 'badge-kegiatan';
              ?>
              <div class="info-stack">
                <span class="badge-agenda <?= $badgeAgenda ?>">
                  <i class="bi bi-bookmark-check"></i>
                  <?= htmlspecialchars($jenisAgenda) ?>
                </span>
                <div class="info-row">
                  <span class="icon-soft">
                    <i class="bi bi-tags"></i>
                  </span>
                  <div>
                    <strong>Kategori</strong><br>
                    <span><?= htmlspecialchars($kategoriAgenda ?: '-') ?></span>
                  </div>
                </div>
                <div class="info-row">
                  <span class="icon-soft red">
                    <i class="bi bi-person-check"></i>
                  </span>
                  <div>
                    <strong>Jenis Pertemuan</strong><br>
                    <span><?= htmlspecialchars($r['meeting_type'] ?: '-') ?></span>
                  </div>
                </div>
              </div>
            </td>
            <td>
              <?php
                $namaPemohon = $r['nama_pegawai'] ? $r['nama_pegawai'] : ($r['nama'] ? $r['nama'] : '-');
                $inisial = strtoupper(substr(trim($namaPemohon), 0, 1));
              ?>
              <div class="requester-card">
                <div class="requester-avatar">
                  <?= htmlspecialchars($inisial) ?>
                </div>
                <div>
                  <div class="requester-name">
                    <?= htmlspecialchars($namaPemohon) ?>
                  </div>
                  <div class="requester-time">
                    <?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['time_stamp']))) ?>
                  </div>
                </div>
              </div>
            </td>
            <td class="text-center">
              <div class="action-wrapper">
                <?php if(!empty($r['file_upload'])): ?>
                  <button class="btn btn-action btn-edit-modern btn-view-file"
                    data-file="<?= htmlspecialchars($r['file_upload']) ?>">
                    <i class="fa fa-file"></i>
                  </button>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
                <button class="btn btn-action btn-edit-modern btn-edit"
                  data-status="<?= $r['status'] ?>"
                  data-id="<?= $r['id'] ?>"
                  data-pertemuan="<?= $r['id_pertemuan'] ?>"
                  data-jenis="<?= $r['meeting_type'] ?>"
                  data-ruang="<?= htmlspecialchars($r['nama_ruang'] ?? '') ?>"
                  data-agenda="<?= htmlspecialchars($r['topik_rapat']) ?>"
                  data-kategori="<?= $r['kategori_rapat'] ?>"
                  data-tanggal="<?= $r['tanggal'] ?>"
                  data-tanggal-selesai="<?= htmlspecialchars($r['tanggal_selesai'] ?? '') ?>"
                  data-fasilitas="<?= htmlspecialchars($r['fasilitas_nama'] ?? '') ?>"
                  data-mulai="<?= $r['waktu_mulai'] ?>"
                  data-selesai="<?= $r['waktu_selesai'] ?>"
                  data-penyelenggara="<?= htmlspecialchars($r['penyelenggara']) ?>"
                  data-narahubung="<?= htmlspecialchars($r['narahubung']) ?>"
                  data-konsumsi="<?= $r['catatan_konsumsi'] ?>"
                  data-ajukan-konsumsi="<?= (int)$r['ajukan_konsumsi'] ?>"
                  data-link="<?= $r['link_zoom'] ?>"
                  data-zoom-short="<?= htmlspecialchars($r['zoom_short_url'] ?? '') ?>"
                  data-passcode="<?= htmlspecialchars($r['passcode'] ?? '') ?>"
                  data-meeting-id="<?= htmlspecialchars($r['zoom_meeting_id'] ?? '') ?>"
                  data-jumlah-peserta="<?= htmlspecialchars($r['jumlah_peserta'] ?? '') ?>"
                  data-file="<?= htmlspecialchars($r['file_upload']) ?>"
                  data-kehadiran="<?= $r['kehadiran'] ?>"
                  data-kepentingan="<?= htmlspecialchars($r['jenis_kepentingan'] ?? '') ?>"
                  data-keterangan="<?= htmlspecialchars($r['keterangan'] ?? '') ?>"
                  data-pendamping="<?= htmlspecialchars($r['pendamping'] ?? '') ?>"
                  data-pelaksana="<?= htmlspecialchars($r['nama_pelaksana'] ?? '') ?>"
                  data-pendamping-text="<?= htmlspecialchars($r['pendamping_text'] ?? '') ?>"
                  data-pelaksana-text="<?= htmlspecialchars($r['nama_pelaksana_text'] ?? '') ?>">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <?php if (
                  $roleUser === 'admin' ||
                  ($roleUser === 'staf khusus' && $r['id_pertemuan'] == 2) ||
                  ($roleUser === 'staf umum')
                ): ?>
                  <button 
                    class="btn btn-action btn-delete-modern btn-delete" 
                    data-id="<?= $r['id'] ?>"
                    data-status="<?= $r['status'] ?>">
                    <i class="bi bi-trash3"></i>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center px-3 pb-3 flex-wrap gap-2">
    <div class="text-muted small">
      Menampilkan <?= ($offset+1) ?> - <?= min($offset+$limit, $totalData) ?> dari <?= $totalData ?> data
    </div>
      <nav>
        <ul class="pagination pagination-sm mb-0 gap-1">
          <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $queryParams ?>&page=<?= $page-1 ?>">
              ‹
            </a>
          </li>
          <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
          ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
              <a class="page-link" href="?<?= $queryParams ?>&page=<?= $i ?>">
                <?= $i ?>
              </a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $queryParams ?>&page=<?= $page+1 ?>">
              ›
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </div>
  </div>
    <div class="modal fade modal-modern" id="editModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="editForm" class="modal-content" enctype="multipart/form-data">
          <div class="modal-header border-0 pb-0">
            <div>
              <h5 class="modal-title mb-1"> Edit Agenda </h5>
              <div class="text-muted small">
                Perbarui informasi agenda kegiatan atau rapat
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editId">
          <input type="hidden" name="action" value="update">
          <div class="edit-section">
            <div class="edit-section-title">
                <div class="edit-section-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>
                  <div>
                    <h6>Informasi Agenda</h6>
                      <div class="edit-section-subtitle">
                        Detail utama agenda kegiatan atau rapat
                      </div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Agenda Rapat</label>
                    <textarea class="form-control no-resize" id="editAgenda" rows="2" name="topik_rapat" readonly></textarea>
                  </div>
                  <div class="row g-3 mb-3">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Jenis Pertemuan</label>
                      <input type="text" class="form-control text-muted" id="editJenis" readonly>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Tempat Pertemuan</label>
                      <input type="text" class="form-control text-muted" id="editRuang" readonly>
                    </div>
                  </div>
                  <div class="row g-3 mb-3">
                    <div class="col-md-3">
                      <label class="form-label fw-semibold">Tanggal Mulai</label>
                      <input type="date" class="form-control" name="tanggal" id="editTanggal">
                    </div>
                    <div class="col-md-3 d-none" id="editTanggalSelesaiWrapper">
                      <label class="form-label fw-semibold">Tanggal Selesai</label>
                      <input type="date" class="form-control" name="tanggal_selesai" id="editTanggalSelesai">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold">Waktu Mulai</label>
                      <input type="time" class="form-control" name="waktu_mulai" id="editMulai">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold">Waktu Selesai</label>
                      <input type="time" class="form-control" name="waktu_selesai" id="editSelesai">
                    </div>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Narahubung</label>
                      <div class="input-group">
                        <input type="text" class="form-control readonly-clickable" id="editNarahubung" name="narahubung" readonly>
                        <button type="button" class="btn btn-success input-action-btn" id="btnOpenWa">
                          <i class="bi bi-whatsapp me-1"></i> WA
                        </button>
                      </div>
                      <small class="text-muted">Klik tombol WA untuk membuka WhatsApp.</small>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Penyelenggara</label>
                      <input type="text" class="form-control text-muted" id="editPenyelenggara" name="penyelenggara">
                    </div>
                  </div>
                </div>
                <div id="zoomInfoWrapper" class="zoom-info-card d-none mt-3">
                  <div class="zoom-info-header">
                    <div class="zoom-info-icon">
                      <i class="bi bi-camera-video"></i>
                    </div>
                    <div>
                      <div class="zoom-info-title">Informasi Zoom Meeting</div>
                      <div class="zoom-info-subtitle">Detail link dan akses meeting online</div>
                    </div>
                  </div>
                  <div class="row g-3 mt-1">
                    <div class="col-md-6">
                      <label class="zoom-label">Link Zoom</label>
                      <div class="input-group">
                        <input type="url" class="form-control readonly-field" name="link_zoom" id="editLink" readonly>
                        <button type="button" class="btn btn-primary input-action-btn" id="btnOpenZoom">
                          <i class="bi bi-box-arrow-up-right me-1"></i> Buka
                        </button>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="zoom-label">Short Link</label>
                      <div class="input-group">
                        <input type="text" class="form-control readonly-field" id="editZoomShort" readonly>
                        <button type="button" class="btn btn-outline-primary input-action-btn" id="btnCopyShort">
                          <i class="bi bi-copy me-1"></i> Salin
                        </button>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="zoom-label">Passcode</label>
                      <input type="text" class="form-control readonly-field" id="editPasscode" readonly>
                    </div>
                    <div class="col-md-4">
                      <label class="zoom-label">Meeting ID</label>
                      <input type="text" class="form-control readonly-field" id="editMeetingId" readonly>
                    </div>
                    <div class="col-md-4">
                      <label class="zoom-label">Jumlah Peserta</label>
                      <input type="text" class="form-control readonly-field" id="editJumlahPeserta" readonly>
                    </div>
                  </div>
                </div>
                <div class="edit-section">
                  <div class="edit-section-title">
                      <div class="edit-section-icon">
                          <i class="bi bi-card-text"></i>
                      </div>
                      <div>
                          <h6>Keterangan & Kehadiran</h6>
                          <div class="edit-section-subtitle">
                              Informasi pelaksana, pendamping, dan catatan kegiatan
                          </div>
                      </div>
                  </div>
                  <div id="detailKegiatanWrapper">
                    <div class="row g-3 mb-3">
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Kehadiran</label>
                        <div class="dropdown w-100">
                          <button class="form-select text-start text-muted custom-input" type="button" id="dropdownKehadiran" data-bs-toggle="dropdown">
                            Pilih Kehadiran
                          </button>
                          <ul class="dropdown-menu w-100">
                            <li><a class="dropdown-item kehadiran-option" data-value="Hadir" data-text="Hadir">Hadir</a></li>
                            <li><a class="dropdown-item kehadiran-option" data-value="Diwakilkan" data-text="Diwakilkan">Diwakilkan</a></li>
                            <li><a class="dropdown-item kehadiran-option" data-value="Tentatif" data-text="Tentatif">Tentatif</a></li>
                            <li><a class="dropdown-item kehadiran-option" data-value="Reschedule" data-text="Reschedule">Reschedule</a></li>
                            <li><a class="dropdown-item kehadiran-option" data-value="Belum Ada Arahan" data-text="Belum Ada Arahan">Belum Ada Arahan</a></li>
                          </ul>
                        </div>
                        <input type="hidden" name="kehadiran" id="kehadiranSelect">
                      </div>
                      <div class="col-md-12 d-none" id="pelaksana_group">
                        <label class="form-label">Nama Pelaksana</label>
                        <select 
                          name="nama_pelaksana[]" 
                          id="nama_pelaksana" 
                          class="form-control custom-input pegawai-select-edit" 
                          multiple>
                          <?php foreach ($pegawaiList as $pegawai): ?>
                            <option value="<?= htmlspecialchars($pegawai['nip_pegawai']); ?>">
                              <?= htmlspecialchars($pegawai['nama_pegawai']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-12 d-none" id="pendamping_group">
                        <label class="form-label">Nama Pendamping</label>
                        <select 
                          name="pendamping[]" 
                          id="pendamping" 
                          class="form-control custom-input pegawai-select-edit" 
                          multiple>
                          <?php foreach ($pegawaiList as $pegawai): ?>
                            <option value="<?= htmlspecialchars($pegawai['nip_pegawai']); ?>">
                              <?= htmlspecialchars($pegawai['nama_pegawai']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="row g-3 mb-3">
                      <div class="col-md-12 d-none" id="editTvWallWrapper">
                        <div class="edit-tvwall-box">
                          <label class="form-label fw-semibold mb-1">
                            Penayangan TV Wall Lobby
                          </label>

                          <div class="text-muted small mb-3">
                            Apakah agenda kegiatan ini ingin ditayangkan di TV Wall Lobby?
                          </div>

                          <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check edit-tvwall-option-box">
                              <input 
                                class="form-check-input edit-tvwall-option" 
                                type="checkbox"
                                id="editTvwallYa"
                                value="0">
                              <label class="form-check-label fw-semibold" for="editTvwallYa">
                                Ya, tampilkan
                              </label>
                            </div>

                            <div class="form-check edit-tvwall-option-box">
                              <input 
                                class="form-check-input edit-tvwall-option" 
                                type="checkbox"
                                id="editTvwallTidak"
                                value="1">
                              <label class="form-check-label fw-semibold" for="editTvwallTidak">
                                Tidak
                              </label>
                            </div>
                          </div>

                          <input type="hidden" name="jenis_kepentingan" id="editJenisKepentingan">
                        </div>
                      </div>
                    </div>
                    <label class="form-label fw-semibold">Keterangan</label>
                    <textarea class="form-control no-resize text-muted" id="editKeterangan" rows="3" name="keterangan"></textarea>
                  </div>
                  <div id="editFasilitasKonsumsiWrapper" class="d-none">
                    <div class="row g-3 mt-2">
                      <div class="col-md-6">
                        <div class="info-box-soft h-100">
                          <div class="info-box-soft-title">
                            <i class="bi bi-tools me-1"></i>
                            Tambahan Fasilitas
                          </div>
                          <textarea 
                            class="form-control no-resize readonly-field" 
                            id="editFasilitas" 
                            rows="4" 
                            readonly></textarea>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="info-box-soft h-100">
                          <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="editCheckKonsumsi" disabled>
                            <label class="form-check-label fw-semibold">
                              Mengajukan Konsumsi
                            </label>
                          </div>

                          <textarea 
                            class="form-control no-resize readonly-field" 
                            id="editKonsumsi" 
                            rows="4" 
                            readonly></textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                  <label class="fw-semibold mt-3"> Upload File Pendukung </label>
                  <div class="input-group custom-upload-group mt-2">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                    </span>
                    <span id="fileName" class="form-control border-start-0 border-end-0 text-muted d-flex align-items-center" style="cursor:pointer;">
                      Pilih file dokumen pendukung
                    </span>
                    <input type="file" class="form-control" id="fileUpload" name="file_upload" hidden>
                    <button class="btn btn-outline-primary fw-medium px-4" type="button" onclick="document.getElementById('fileUpload').click()">
                      CARI FILE
                    </button>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-light btn-cancel-modern" data-bs-dismiss="modal" type="button" onclick="resetEditModalState()">Batal</button>
                <button class="btn btn-save-modern text-white" type="submit">
                  <i class="fa fa-save me-1"></i> Simpan Perubahan
                </button>
              </div>
            </form>
          </div>
        </div>
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content delete-modal">
                <div class="modal-header border-0">
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
              <div class="modal-body text-center p-4">
                <div class="icon-wrapper mb-3">
                <i class="fas fa-trash"></i>
              </div>
              <h5 class="fw-semibold mb-2">Konfirmasi Penghapusan</h5>
              <p class="text-muted small mb-4">
                Apakah Anda yakin ingin menghapus agenda ini? <br>
                Tindakan ini tidak dapat dibatalkan.
              </p>
              </div>
              <div class="d-flex justify-content-center gap-2 mb-2">
                <button class="btn btn-secondary btn-cancel" data-bs-dismiss="modal">Batal</button>
                <button id="confirmReject" class="btn btn-danger">Hapus</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal fade file-preview-modal" id="fileModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title mb-0">Preview Dokumen</h5>
                <small class="text-muted">Lihat file pendukung agenda</small>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div id="fileViewer" class="file-preview-box d-flex align-items-center justify-content-center">
                <div class="text-muted">File belum dipilih</div>
              </div>
            </div>
          </div>
        </div>
      </div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    const ROLE_USER = "<?= $roleUser ?>";
    let existingFileName = '';
    let rejectId=null;
    const toastEl = document.getElementById('toastSuccess');
    const toastSuccess = toastEl ? new bootstrap.Toast(toastEl) : null;
    function setFieldState(selector, editable) {
     const el = $(selector);

      el.each(function () {
        const field = $(this);

      field
        .removeClass('field-editable field-readonly readonly-field')
        .prop('readonly', !editable)
        .prop('disabled', false);

        if (editable) {
          field.addClass('field-editable');
        } else {
          field.addClass('field-readonly');
        }
        field.closest('.col-md-4, .col-md-6, .col-12, .mb-3').find('.field-status').remove();

        field.closest('.col-md-4, .col-md-6, .col-12, .mb-3').append(`
          <div class="field-status ${editable ? 'editable' : 'readonly'}">
            <i class="bi ${editable ? 'bi-pencil-square' : 'bi-lock'}"></i>
            ${editable ? 'Bisa diedit' : 'Tidak bisa diedit'}
          </div>
        `);
      });
    }
    $('.btn-edit').on('click', function () {
      const status = $(this).data('status');
      const role   = ROLE_USER;
      const idPertemuan = $(this).data('pertemuan');
      const isStafKhususAgendaRapat = role === 'staf khusus' && idPertemuan == 1;
      
      $('#editModal').data('pertemuan', idPertemuan);
      const fasilitas = $(this).attr('data-fasilitas') || '';
      const ajukanKonsumsi = $(this).attr('data-ajukan-konsumsi') || '0';
      const konsumsi = $(this).attr('data-konsumsi') || '';

      if (idPertemuan == 1) {
        $('#editFasilitasKonsumsiWrapper').removeClass('d-none');

        $('#editFasilitas').val(fasilitas ? fasilitas : 'Tidak ada fasilitas tambahan');
        $('#editCheckKonsumsi').prop('checked', ajukanKonsumsi == '1');
        $('#editKonsumsi').val(konsumsi ? konsumsi : 'Tidak ada catatan konsumsi');

        $('#editFasilitas')
          .prop('readonly', true)
          .prop('disabled', false)
          .addClass('readonly-field');

        $('#editKonsumsi')
          .prop('readonly', true)
          .prop('disabled', false)
          .addClass('readonly-field');

        $('#editCheckKonsumsi')
          .prop('disabled', true);
      } else {
        $('#editFasilitasKonsumsiWrapper').addClass('d-none');

        $('#editFasilitas').val('');
        $('#editCheckKonsumsi').prop('checked', false);
        $('#editKonsumsi').val('');
      }
      if (idPertemuan == 2) {
        $('#detailKegiatanWrapper').removeClass('d-none');
      } else {
        $('#detailKegiatanWrapper').addClass('d-none');

        $('#kehadiranSelect').val('');
        $('#dropdownKehadiran').text('Pilih Kehadiran').addClass('text-muted');

        $('#pendamping').val('');
        $('#nama_pelaksana').val('');
        $('#editKeterangan').val('');

        $('#pendamping_group').addClass('d-none');
        $('#pelaksana_group').addClass('d-none');
      }

      const modalEl = document.getElementById('editModal');
      const modalContent = modalEl.querySelector('.modal-content');

      modalContent.classList.remove('locked-modal');
      $('.readonly-wrapper').removeClass('readonly-wrapper');

      $('#editModal input, #editModal textarea, #editModal select')
        .prop('readonly', false)
        .prop('disabled', false)
        .removeClass('readonly-field');

      
      $('#editFasilitas, #editKonsumsi')
        .prop('readonly', true)
        .prop('disabled', false)
        .addClass('readonly-field');
      $('#editCheckKonsumsi')
        .prop('disabled', true);

    if (status === 0) {
      $('#editModal .modal-content').addClass('locked-modal');

      $('#editTanggal, #editMulai, #editSelesai, #editLink, #fileUpload, #fileName, #editAgenda, #editPendamping, #editKeterangan')
        .prop('disabled', true)
        .prop('readonly', true)
    }
    if (status == 1) {
      if (role === 'user') {
        $('#editModal input, #editModal textarea, #editModal select')
          .prop('readonly', true)
          .prop('disabled', true)
          .addClass('readonly-field');

        $('#fileUpload, #fileName')
          .prop('readonly', false)
          .prop('disabled', false)
          .removeClass('readonly-field')
          .css({
            background: '#fff',
            color: '#000',
            pointerEvents: 'auto'
          });
          $('#editForm .btn-save').prop('disabled', false).show();
          }
        
        if (role === 'admin' || role === 'staf khusus' || role === 'staf umum') {

        const lockedFields = [
          '#editJenis',
          '#editRuang',
          '#editKategori',
          '#editPenyelenggara',
          '#editNarahubung',
        ];
        $('#editNarahubung')
          .prop('readonly', true)
          .prop('disabled', false)
          .removeClass('readonly-field field-readonly')
          .addClass('readonly-clickable');

        $('#btnOpenWa').prop('disabled', false);
        $('#btnOpenZoom').prop('disabled', false);
        $('#btnCopyShort').prop('disabled', false);

        lockedFields.forEach(id => {
          $(id)
            .prop('readonly', true)
            .addClass('readonly-field')
            .closest('.col-md-6,.col-12')
            .addClass('readonly-wrapper');
        });

        const editableFields = [
          '#editTanggal',
          '#editMulai',
          '#editSelesai',
          '#editLink',
          '#fileUpload',
          '#editAgenda',
          '#editKeterangan',
          '#editPendamping'
        ];

        editableFields.forEach(id => {
          $(id)
            .prop('readonly', false)
            .prop('disabled', false)
            .removeClass('readonly-field')
            .css({
              background: '#fff',
              color: '#000',
              pointerEvents: 'auto'
            })
            .closest('.col-md-6,.col-12')
            .removeClass('readonly-wrapper');
          });
        }
      }
      const fileUpload = $(this).attr('data-file');
      const fileInput  = document.getElementById('fileUpload');
      const fileNameInput = document.getElementById('fileName');

      fileInput.value = '';
      fileNameInput.value = '';

      existingFileName = '';

      if (fileUpload && fileUpload.trim() !== '') {
        existingFileName = fileUpload.split('/').pop();
        $('#fileName').text(existingFileName || 'Belum ada file');
      } else {
        existingFileName = '';
        $('#fileName').text('Pilih file dokumen pendukung');
      }
      const jenis = $(this).attr('data-jenis');

      const linkZoom = $(this).attr('data-link') || '';
      const zoomShort = $(this).attr('data-zoom-short') || '';
      const passcode = $(this).attr('data-passcode') || '';
      const meetingId = $(this).attr('data-meeting-id') || '';
      const jumlahPeserta = $(this).attr('data-jumlah-peserta') || '';

      $('#editLink').val(linkZoom);
      $('#editZoomShort').val(zoomShort || '-');
      $('#editPasscode').val(passcode || '-');
      $('#editMeetingId').val(meetingId || '-');
      $('#editJumlahPeserta').val(jumlahPeserta || '-');

      const jenisLower = jenis ? jenis.toLowerCase().trim() : '';
      const idPertemuanInt = parseInt(idPertemuan);

      const showZoomInfo =
      (idPertemuanInt === 1 && (jenisLower === 'online' || jenisLower === 'hybrid')) ||
      (idPertemuanInt === 2 && (jenisLower === 'online' || jenisLower === 'hybrid luar kantor'));

        if (showZoomInfo) {
          $('#zoomInfoWrapper').removeClass('d-none');

          $('#editLink').val(linkZoom || '-');
          $('#editZoomShort').val(zoomShort || '-');
          $('#editPasscode').val(passcode || '-');
          $('#editMeetingId').val(meetingId || '-');
          $('#editJumlahPeserta').val(jumlahPeserta || '-');
        } else {
          $('#zoomInfoWrapper').addClass('d-none');

          $('#editLink').val('');
          $('#editZoomShort').val('');
          $('#editPasscode').val('');
          $('#editMeetingId').val('');
        }

        $('#editId').val($(this).attr('data-id'));
        $('#editJenis').val(jenis);
        $('#editRuang').val($(this).attr('data-ruang'));
        
        $('#editId').val($(this).attr('data-id'));
        $('#editJenis').val(jenis);
        $('#editRuang').val($(this).attr('data-ruang'));
        $('#editAgenda').val($(this).attr('data-agenda'));
        $('#editKategori').val($(this).attr('data-kategori'));
        $('#editTanggal').val($(this).attr('data-tanggal'));
        const tanggalMulaiEdit = $(this).attr('data-tanggal') || '';
        const tanggalSelesaiEdit = $(this).attr('data-tanggal-selesai') || tanggalMulaiEdit;

        $('#editTanggal').val(tanggalMulaiEdit);
        $('#editTanggalSelesai').val(tanggalSelesaiEdit);

        if (idPertemuan == 2) {
          $('#editTanggalSelesaiWrapper').removeClass('d-none');
        } else {
          $('#editTanggalSelesaiWrapper').addClass('d-none');
          $('#editTanggalSelesai').val(tanggalMulaiEdit);
        }
        $('#editMulai').val($(this).attr('data-mulai'));
        $('#editSelesai').val($(this).attr('data-selesai'));
        $('#editPenyelenggara').val($(this).attr('data-penyelenggara'));
        $('#editNarahubung').val($(this).attr('data-narahubung'));
        $('#editLink').val($(this).attr('data-link'));
        $('#editKeterangan').val($(this).attr('data-keterangan'));
        $('#editKehadiran').val($(this).attr('data-kehadiran'));
        $('#editPendamping').val($(this).attr('data-pendamping'));
        function parseNipList(value) {
          return String(value || '')
          .replace(/\s+/g, '')
          .split(',')
          .map(item => item.trim())
          .filter(Boolean);
        }
      const pendamping = parseNipList($(this).attr('data-pendamping'));
      const pelaksana  = parseNipList($(this).attr('data-pelaksana'));

      $('#pendamping').val(pendamping).trigger('change.select2');
      $('#nama_pelaksana').val(pelaksana).trigger('change.select2');

      const kepentinganFinal = $(this).attr('data-kepentingan');

      $('.edit-tvwall-option').prop('checked', false);

      if (idPertemuan == 2) {
        $('#editTvWallWrapper').removeClass('d-none');

        if (kepentinganFinal == '0') {
          $('#editTvwallYa').prop('checked', true);
          $('#editJenisKepentingan').val('0');
        } else if (kepentinganFinal == '1') {
          $('#editTvwallTidak').prop('checked', true);
          $('#editJenisKepentingan').val('1');
        } else {
          $('#editJenisKepentingan').val('');
        }
      } else {
        $('#editTvWallWrapper').addClass('d-none');
        $('#editJenisKepentingan').val('0');
      }
      if (role === 'staf khusus' && idPertemuan == 1) {
        $('#editModal input, #editModal textarea, #editModal select')
          .prop('readonly', true)
          .prop('disabled', true)
          .addClass('readonly-field');

        $('#editId')
          .prop('disabled', false)
          .prop('readonly', false);

        $('#fileUpload, #fileName')
          .prop('readonly', false)
          .prop('disabled', false)
          .removeClass('readonly-field')
          .css({
            background: '#fff',
            color: '#000',
            pointerEvents: 'auto',
            cursor: 'pointer'
          });

        $('#editModal button[type="submit"]').prop('disabled', false).show();

        $('#editTanggalSelesaiWrapper').addClass('d-none');
        $('#editTvWallWrapper').addClass('d-none');
      }

      new bootstrap.Modal(document.getElementById('editModal')).show();
    });

    function clearEditScheduleError() {
      $('#editTanggal, #editTanggalSelesai, #editMulai, #editSelesai')
        .removeClass('is-field-invalid is-invalid');

      $('#editScheduleError').remove();
    }

    $('#editTanggal, #editTanggalSelesai, #editMulai, #editSelesai').on('input change', function () {
      clearEditScheduleError();
    });

    $('#editTanggal, #editMulai, #editSelesai').on('input change', function () {
      clearEditScheduleError();
    });

    function showEditScheduleError(message) {
      clearEditScheduleError();

      $('#editTanggal, #editTanggalSelesai, #editMulai, #editSelesai')
        .addClass('is-field-invalid');

      $('#editSelesai').closest('.col-md-3, .col-md-4').append(`
        <div id="editScheduleError" class="field-error">
          ${message}
        </div>
      `);
    }
    function resetEditModalState() {
      clearEditScheduleError();

      $('#editForm')[0].reset();

      $('#editModal .modal-content').removeClass('locked-modal');
      $('.readonly-wrapper').removeClass('readonly-wrapper');

      $('#editTanggal, #editTanggalSelesai, #editMulai, #editSelesai')
        .removeClass('is-field-invalid is-invalid');

      $('#editScheduleError').remove();

      $('#fileUpload').val('');
      $('#fileName').text(existingFileName || 'Pilih file dokumen pendukung');

      $('#pendamping').val(null).trigger('change.select2');
      $('#nama_pelaksana').val(null).trigger('change.select2');

      $('#pendamping_group').addClass('d-none');
      $('#pelaksana_group').addClass('d-none');

      $('#dropdownKehadiran')
        .text('Pilih Kehadiran')
        .addClass('text-muted');

      $('#kehadiranSelect').val('');
    }
    $('#editModal').on('hidden.bs.modal', function () {
      resetEditModalState();
    });

    $("#editForm").on("submit", function(e){
      e.preventDefault();

      if ($('#editModal .modal-content').hasClass('locked-modal')) {
        return false;
      }

      clearEditScheduleError();

      const idPertemuanEdit = $('#editModal').data('pertemuan') || '';

      if (ROLE_USER === 'user') {
        const formData = new FormData(document.getElementById('editForm'));
        formData.append("action", "update");
        formData.append("id", $('#editId').val());

        $.ajax({
          url: "",
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
          success: function(r){
            if(r.success){
              $("#editModal").modal('hide');
              setTimeout(() => location.reload(), 500);
            } else {
              alert(r.message || "Gagal menyimpan dokumen.");
            }
          },
          error: function(xhr){
            console.log(xhr.responseText);
            alert("Terjadi kesalahan saat menyimpan.");
          }
        });

        return false;
      }

      if (ROLE_USER === 'staf khusus' && idPertemuanEdit == 1) {
        const formData = new FormData(document.getElementById('editForm'));
        formData.append("action", "update");
        formData.append("id", $('#editId').val());

        $.ajax({
          url: "",
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
          success: function(r){
            if(r.success){
              $("#editModal").modal('hide');
              setTimeout(() => location.reload(), 500);
            } else {
              alert(r.message || "Gagal menyimpan dokumen.");
            }
          },
          error: function(xhr){
            console.log(xhr.responseText);
            alert("Terjadi kesalahan saat menyimpan.");
          }
        });

        return false;
      }
      if (idPertemuanEdit == 2 && $('#editJenisKepentingan').val() === '') {
        showEditScheduleError('Pilihan penayangan TV Wall wajib dipilih.');
        return false;
      }
      const tanggalMulai = $('#editTanggal').val();
      let tanggalSelesai = $('#editTanggalSelesai').val() || tanggalMulai;

      if (idPertemuanEdit == 1) {
        tanggalSelesai = tanggalMulai;
        $('#editTanggalSelesai').val(tanggalMulai);
      }

      if (idPertemuanEdit == 2 && tanggalSelesai < tanggalMulai) {
        showEditScheduleError('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        return false;
      }

      if (tanggalMulai === '' || $('#editMulai').val() === '' || $('#editSelesai').val() === '') {
        showEditScheduleError('Tanggal, waktu mulai, dan waktu selesai wajib diisi.');
        return false;
      }

      if (tanggalSelesai < tanggalMulai) {
        showEditScheduleError('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        return false;
      }

      if ($('#editSelesai').val() <= $('#editMulai').val()) {
        showEditScheduleError('Waktu selesai harus lebih besar dari waktu mulai.');
        return false;
      }

      if ($('#detailKegiatanWrapper').hasClass('d-none')) {
        $('#kehadiranSelect').val('');
        $('#pendamping').val(null).trigger('change.select2');
        $('#nama_pelaksana').val(null).trigger('change.select2');
        $('#editKeterangan').val('');
      }

      $.post('', {
        action: 'cek_bentrok_edit',
        id: $('#editId').val(),
        tanggal: tanggalMulai,
        tanggal_selesai: tanggalSelesai,
        waktu_mulai: $('#editMulai').val(),
        waktu_selesai: $('#editSelesai').val()
      }, function(res) {

        if (!res.success) {
          showEditScheduleError(res.message || 'Jadwal ruangan sudah digunakan.');
          return;
        }

        const formData = new FormData(document.getElementById('editForm'));
        formData.append("action", "update");

        $.ajax({
          url: "",
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
          success: function(r){
            if(r.success){
              $("#editModal").modal('hide');
              setTimeout(() => location.reload(), 500);
            } else {
              showEditScheduleError(r.message || "Gagal menyimpan perubahan.");
            }
          },
          error: function(){
            showEditScheduleError("Terjadi kesalahan saat menyimpan.");
          }
        });

      }, 'json').fail(function () {
        showEditScheduleError('Gagal mengecek jadwal ruangan.');
      });
    });
    $(".btn-delete").on("click", function() {
      if ($(this).data('status') == 0) {
        alert('Data sudah dibatalkan');
        return;
      }
      rejectId = $(this).data("id");
      new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });

    $("#confirmReject").on("click",function(){
      if(!rejectId)return;
      $.post("",{action:"reject",id:rejectId},function(r){
        if(r.success){
          $("#rejectModal").modal('hide');
          location.reload();
        }else alert(r.message||"Gagal memperbarui.");
      },"json");
    });

    $(document).on('click', '.btn-view-file', function () {
      const file = $(this).data('file');

      if (!file) {
        alert('File tidak tersedia');
        return;
      }

      const ext = file.split('.').pop().toLowerCase();
      let content = '';

      if (['png', 'jpg', 'jpeg', 'webp'].includes(ext)) {
        content = `<img src="${file}" class="preview-img">`;
      } else if (ext === 'pdf') {
        content = `<iframe src="${file}#toolbar=1" class="preview-iframe"></iframe>`;
      } else {
        content = `
          <div class="text-center p-4">
            <i class="bi bi-file-earmark-text fs-1 text-primary d-block mb-3"></i>
            <p class="text-muted mb-3">Preview tidak tersedia untuk tipe file ini.</p>
            <a href="${file}" target="_blank" class="btn btn-primary">
              <i class="bi bi-box-arrow-up-right me-1"></i> Buka Dokumen
            </a>
          </div>
        `;
      }

      $('#fileViewer').html(content);
      new bootstrap.Modal(document.getElementById('fileModal')).show();
    });
    $('#btnChangeFile').on('click', function(e){
      e.preventDefault();
      $('#fileInputReplacement').click();
    });
    $('#fileUpload').on('change', function () {
      if (this.files && this.files.length > 0) {
        const newFileName = this.files[0].name;
        $('#fileName').text(newFileName);
        existingFileName = newFileName;
      } else {
        $('#fileName').text(existingFileName || 'Belum ada file');
      }
    });
    $('#fileName').on('click', function () {
      if (!$('#fileUpload').prop('disabled')) {
        $('#fileUpload').trigger('click');
      }
    });
    let searchTimeout = null;

    $('#searchInput').on('input', function () {
      clearTimeout(searchTimeout);

      searchTimeout = setTimeout(() => {
        const val = $(this).val().trim();
        if (val === '') {
          $('#searchForm').submit();
          return;
        }
        $('#searchForm').submit();
      }, 5000);
    });
    $(function () {
      $('.bulan-option').on('click', function () {
        const value = $(this).data('value');
        const text = $(this).data('text');
        $('#dropdownBulan').text(text);
        $('#bulanSelect').val(value);
      });
      $('.tahun-option').on('click', function () {
        const value = $(this).data('value');
        const text = $(this).data('text');
        $('#dropdownTahun').text(text);
        $('#tahunSelect').val(value);
      });
    });
    $(function () {
      $('.kehadiran-option').on('click', function () {
        const value = $(this).data('value');
        const text = $(this).data('text');
        $('#dropdownKehadiran').text(text);
        $('#kehadiranSelect').val(value);
      });
    });

    $(".btn-edit").on("click", function () {
      const kehadiran = $(this).data("kehadiran") || "";
      $("#dropdownKehadiran").text(kehadiran ? kehadiran : "Pilih Kehadiran");
      $("#kehadiranSelect").val(kehadiran);

      updateKehadiranFields(kehadiran);
    });

    function updateKehadiranFields(kehadiran) {
      const pelaksanaGroup = $("#pelaksana_group");
      const pendampingGroup = $("#pendamping_group");

      if (kehadiran === 'Hadir' || kehadiran === 'Diwakilkan') {
        $('#pelaksana_group').removeClass('d-none');
        $('#pendamping_group').removeClass('d-none');
      } else {
        $('#pelaksana_group').addClass('d-none');
        $('#pendamping_group').addClass('d-none');

        $('#nama_pelaksana').val(null).trigger('change');
        $('#pendamping').val(null).trigger('change');
      }
    }
    $(function () {
      $('.pegawai-select-edit').select2({
        width: '100%',
        placeholder: 'Ketik dan pilih nama pegawai',
        allowClear: true,
        closeOnSelect: false,
        dropdownParent: $('#editModal')
      });
    });
    $(function () {
        $('.pegawai-select').select2({
          width: '100%',
          placeholder: function () {
            return $(this).data('placeholder');
          },
          allowClear: true,
          closeOnSelect: false,
          dropdownParent: $('#formPengajuanRapat')
        });
    });
    $(function () {
      $(".kehadiran-option").on("click", function () {
        const value = $(this).data("value");
        $("#dropdownKehadiran").text(value);
        $("#kehadiranSelect").val(value);
        updateKehadiranFields(value);
      });
        
    });
    function cleanPhoneNumber(number) {
      return String(number || '').replace(/[^0-9]/g, '');
    }

    $('#btnOpenWa').on('click', function () {
      const phone = cleanPhoneNumber($('#editNarahubung').val());
      if (!phone) {
        alert('Nomor narahubung tidak tersedia.');
        return;
      }
      window.open('https://wa.me/' + phone, '_blank');
    });

    $('#btnOpenZoom').on('click', function () {
      let link = $('#editLink').val().trim();

      if (!link || link === '-') {
        alert('Link Zoom tidak tersedia.');
        return;
      }

      window.open(link, '_blank');
    });

    $('#btnCopyShort').on('click', function () {
      const shortLink = $('#editZoomShort').val().trim();

      if (!shortLink || shortLink === '-') {
        alert('Short link tidak tersedia.');
        return;
      }

      navigator.clipboard.writeText(shortLink).then(function () {
        $('#btnCopyShort')
          .html('<i class="bi bi-check2 me-1"></i> Tersalin')
          .removeClass('btn-outline-primary')
          .addClass('btn-success');

        setTimeout(function () {
          $('#btnCopyShort')
            .html('<i class="bi bi-copy me-1"></i> Salin')
            .removeClass('btn-success')
            .addClass('btn-outline-primary');
        }, 1500);
      });
    });
    $(document).on('change', '.edit-tvwall-option', function () {
    $('.edit-tvwall-option').not(this).prop('checked', false);

    if ($(this).is(':checked')) {
      $('#editJenisKepentingan').val($(this).val());
    } else {
      $('#editJenisKepentingan').val('');
    }
  });
  </script>
</body>
</html>