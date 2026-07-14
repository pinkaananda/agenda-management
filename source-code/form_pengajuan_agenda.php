<?php
    session_start();

    // $lokasiRoot = "/home/u299101980/domains/demenumkm.info/public_html/";
    // include $lokasiRoot . 'controller/user.php';

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    date_default_timezone_set('Asia/Jakarta');

    include_once __DIR__ . "/koneksi.php";
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
    include_once "C:/xampp/htdocs/rapat/functions.php";
    } else {
        include_once "/home/u299101980/domains/demenumkm.info/public_html/function.php";
    }

    // $input_date  = date('Y-m-d H:i:s');
    // $kode_satker = $satker;
    // $nip_pemohon = $nip;

    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        include "koneksi.php";
        $kode_satker = '694762';
        $nip_pemohon = '1810014312020002';
    }

    $MAX_UPLOAD_SIZE = 5 * 1024 * 1024;
    $ALLOWED_EXTENSIONS = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg'];
    function showMessage($title, $message)
    {
        echo "
        <script>
            alert('$title\n\n$message');
            history.back();
        </script>
        ";
        exit;
    }
    function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    function normalizeArray($data)
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        return array_values(
            array_unique(
                array_filter(
                    array_map('trim', $data)
                )
            )
        );
    }

    function cekBentrok($conn, $id_ruang, $tanggalMulai, $tanggalSelesai, $mulai, $selesai)
{
    if ($tanggalSelesai === '') {
        $tanggalSelesai = $tanggalMulai;
    }

    $sql = "
        SELECT 1
        FROM tb_agenda_rapat
        WHERE status = 1
        AND FIND_IN_SET(?, id_ruang_rapat)
        AND (
            tanggal <= ?
            AND COALESCE(NULLIF(tanggal_selesai, ''), tanggal) >= ?
        )
        AND NOT (
            waktu_selesai <= ?
            OR waktu_mulai >= ?
        )
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssss',
        $id_ruang,
        $tanggalSelesai,
        $tanggalMulai,
        $mulai,
        $selesai
    );
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

    function getDefaultProvinsi($conn)
    {
        $default = [
            'id_provinsi'   => '',
            'nama_provinsi' => 'DKI Jakarta'
        ];
        $stmt = $conn->prepare("
            SELECT id_provinsi, nama_provinsi
            FROM tb_provinsi
            WHERE nama_provinsi = 'DKI Jakarta'
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: $default;
    }

    function getDefaultKabupaten($conn)
    {
        $default = [
            'id_kabupaten'   => '3174',
            'nama_kabupaten' => 'Kota Adm. Jakarta Selatan',
            'kode_kota'      => '1',
            'id_provinsi'    => '31'
        ];
        $stmt = $conn->prepare("
            SELECT
                id_kabupaten,
                id_provinsi,
                nama_kabupaten,
                kode_kota
            FROM tb_kabupaten
            WHERE nama_kabupaten = 'Kota Adm.Jakarta Selatan'
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: $default;
    }

    function uploadDokumen($file, $allowedExt, $maxSize)
    {
        if (empty($file['name'])) {
            return '';
        }
        if ($file['size'] > $maxSize) {
            popupRedirect(
                'warning',
                'Ukuran file terlalu besar',
                'Ukuran file maksimal 5MB.'
            );
        }
        if (!is_dir('dokumen')) {
            mkdir('dokumen', 0777, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            popupRedirect(
                'warning',
                'Format file tidak valid',
                'Format file tidak diizinkan.'
            );
        }

        $uniqueName = uniqid('doc_', true) . '.' . $ext;
        $uploadPath = 'dokumen/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            popupRedirect(
                'error',
                'Upload gagal',
                'Gagal mengunggah file.'
            );
        }
        return $uploadPath;
    }
    if (!function_exists('createZoomMeeting')) {
        function createZoomMeeting($topic, $tanggal, $waktuMulai, $waktuSelesai, $requestedPassword = '', $hostUser = '') {
            if (!function_exists('getZoomAccessToken')) {
                return [
                    'success' => false,
                    'message' => 'Fungsi getZoomAccessToken() belum tersedia.'
                ];
            }

            $token = getZoomAccessToken();

            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Gagal mendapatkan access token Zoom.'
                ];
            }

            $start = strtotime($tanggal . ' ' . $waktuMulai);
            $end   = strtotime($tanggal . ' ' . $waktuSelesai);

            if (!$start || !$end || $end <= $start) {
                return [
                    'success' => false,
                    'message' => 'Waktu selesai harus lebih besar dari waktu mulai.'
                ];
            }

            $duration = ceil(($end - $start) / 60);

            $payload = [
                'topic'      => $topic,
                'type'       => 2,
                'start_time' => date('Y-m-d\TH:i:s', $start),
                'duration'   => $duration,
                'timezone'   => 'Asia/Jakarta',
                'settings'   => [
                    'join_before_host' => true,
                    'waiting_room'     => false,
                    'mute_upon_entry'  => true,
                    'approval_type'    => 2
                ]
            ];

            if ($requestedPassword !== '') {
                $payload['password'] = $requestedPassword;
            }

            $userId = $hostUser !== ''
                ? $hostUser
                : (defined('ZOOM_HOST_USER') && ZOOM_HOST_USER !== '' ? ZOOM_HOST_USER : 'me');

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => "https://api.zoom.us/v2/users/" . urlencode($userId) . "/meetings",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json"
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $err = curl_error($ch);
                curl_close($ch);

                return [
                    'success' => false,
                    'message' => 'CURL Error Zoom: ' . $err
                ];
            }

            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300 && !empty($data['join_url'])) {
                return [
                    'success'    => true,
                    'join_url'   => $data['join_url'],
                    'start_url'  => $data['start_url'] ?? '',
                    'meeting_id' => $data['id'] ?? '',
                    'password'   => $data['password'] ?? ''
                ];
            }

            return [
                'success'   => false,
                'message'   => $data['message'] ?? 'Gagal membuat Zoom Meeting.',
                'http_code' => $httpCode,
                'raw'       => $data
            ];
        }
    }

    if (isset($_GET['action'])) {

        if ($_GET['action'] === 'get_available_rooms') {
            $meetingType = $_GET['meeting_type'] ?? 'Offline';
            $tanggal     = $_GET['tanggal'] ?? '';
            $tanggalSelesai = $_GET['tanggal_selesai'] ?? '';
            $mulai       = $_GET['mulai'] ?? '';
            $selesai     = $_GET['selesai'] ?? '';

            if (!$tanggal || !$mulai || !$selesai) {
                jsonResponse([]);
            }
            if ($tanggalSelesai === '') {
                $tanggalSelesai = $tanggal;
            }

            $tipe = [1];

            if ($meetingType === 'Online') {
                $tipe = [0];
            }
            if ($meetingType === 'Hybrid') {
                $tipe = [0, 1];
            }

            $placeholders = implode(',', array_fill(0, count($tipe), '?'));
            $sql = "
                SELECT
                    rr.id_ruang,
                    rr.nama_ruang,
                    rr.kd_satker,
                    s.URAIAN_SATKER
                FROM tb_ruangan_rapat rr
                LEFT JOIN tb_satker s
                    ON rr.kd_satker = s.KDSATKER
                WHERE rr.tipe_ruangan IN ($placeholders)
                AND NOT EXISTS (
                    SELECT 1
                    FROM tb_agenda_rapat ar
                    WHERE ar.status = 1
                    AND FIND_IN_SET(rr.id_ruang, ar.id_ruang_rapat)
                    AND ar.tanggal <= ?
                    AND COALESCE(NULLIF(ar.tanggal_selesai, ''), ar.tanggal) >= ?
                    AND NOT (
                        ar.waktu_selesai <= ?
                        OR ar.waktu_mulai >= ?
                    )
                )
                ORDER BY s.URAIAN_SATKER, rr.nama_ruang
            ";

            $stmt = $conn->prepare($sql);

            if (count($tipe) === 1) {
                $stmt->bind_param(
                    'issss',
                    $tipe[0],
                    $tanggalSelesai,
                    $tanggal,
                    $mulai,
                    $selesai
                );
            } else {
                $stmt->bind_param(
                    'iissss',
                    $tipe[0],
                    $tipe[1],
                    $tanggalSelesai,
                    $tanggal,
                    $mulai,
                    $selesai
                );
            }

            $stmt->execute();
            $query = $stmt->get_result();
            $grouped = [];

            while ($row = $query->fetch_assoc()) {
                $group = $row['URAIAN_SATKER'] ?: 'Lainnya';
                $grouped[$group][] = [
                    'id_ruang'   => $row['id_ruang'],
                    'nama_ruang' => $row['nama_ruang']
                ];
            }
            jsonResponse($grouped);
        }

        if ($_GET['action'] === 'get_zoom_rooms') {
            $stmt = $conn->prepare("
                SELECT id_ruang, nama_ruang
                FROM tb_ruangan_rapat
                WHERE tipe_ruangan = 0
                ORDER BY nama_ruang ASC
            ");

            $stmt->execute();
            $res = $stmt->get_result();

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }

            jsonResponse($data);
        }

        if ($_GET['action'] === 'get_filtered_fasilitas') {
            $tanggal = $_GET['tanggal'] ?? '';
            if (!$tanggal) {
                jsonResponse([]);
            }

            $stmt = $conn->prepare("
                SELECT
                    b.id_bmn,
                    b.spek_bmn
                FROM daftar_bmn b
                WHERE b.is_pinjampakai = 1
                AND b.kd_satker = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM pengajuan_bmn_detail d
                    JOIN pengajuan_bmn p
                        ON d.id_pengajuan = p.id_pengajuan
                    WHERE d.id_bmn = b.id_bmn
                    AND p.status IN ('Disetujui','Diajukan')
                    AND ? BETWEEN p.tgl_pinjam AND p.tgl_kembali
                )
                ORDER BY b.spek_bmn ASC
            ");

            $stmt->bind_param(
                "ss",
                $kode_satker,
                $tanggal
            );

            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            jsonResponse($data);
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'test_zoom') {
        header('Content-Type: application/json; charset=utf-8');

        $tanggalTest = date('Y-m-d', strtotime('+1 day'));

        $zoom = createZoomMeeting(
            'TEST Generate Zoom Lokal',
            $tanggalTest,
            '07:00',
            '07:10',
            '123456'
        );

        echo json_encode($zoom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ruangIds = normalizeArray($_POST['id_ruang'] ?? []);
        $fasilitasArr = normalizeArray($_POST['fasilitas'] ?? []);
        $pendampingArr = normalizeArray($_POST['pendamping'] ?? []);
        $pelaksanaArr = normalizeArray($_POST['nama_pelaksana'] ?? []);
        $defaultProvinsi  = getDefaultProvinsi($conn);
        $defaultKabupaten = getDefaultKabupaten($conn);

        $data = [
            'topik'             => trim($_POST['topik_rapat'] ?? ''),
            'tanggal'           => trim($_POST['tanggal'] ?? ''),
            'tanggal_selesai'   => trim($_POST['tanggal_selesai'] ?? ''),
            'mulai'             => trim($_POST['waktu_mulai'] ?? ''),
            'selesai'           => trim($_POST['waktu_selesai'] ?? ''),
            'penyelenggara'     => trim($_POST['penyelenggara'] ?? ''),
            'narahubung'        => trim($_POST['narahubung'] ?? ''),
            'passcode'          => trim($_POST['passcode'] ?? ''),
            'meetingType'       => trim($_POST['meeting_type'] ?? ''),
            'linkZoom'          => trim($_POST['link_zoom'] ?? ''),
            'ajukan'            => isset($_POST['ajukan_konsumsi']) ? 1 : 0,
            'catatan'           => trim($_POST['catatan_konsumsi'] ?? ''),
            'jenis_agenda'      => $_POST['jenis_agenda'] ?? '',
            'kategori_rapat'    => trim($_POST['kategori_rapat'] ?? ''),
            'kepentingan'       => $_POST['kepentingan'] ?? '',
            'kehadiran'         => $_POST['kehadiran'] ?? '',
            'keterangan'        => $_POST['keterangan'] ?? '',
            'nama_tempat'       => trim($_POST['nama_tempat'] ?? ''),
            'jumlah_peserta'    => trim($_POST['jumlah_peserta'] ?? ''),
        ];

        if (
            $data['topik'] === '' ||
            $data['tanggal'] === '' ||
            $data['mulai'] === '' ||
            $data['selesai'] === '' ||
            $data['penyelenggara'] === '' ||
            $data['meetingType'] === ''
        ) {
            showMessage(
            'Data belum lengkap',
            'Mohon lengkapi seluruh field wajib.'
        );
        }
        if (strlen($data['topik']) > 255) {
            popupRedirect(
                'warning',
                'Topik terlalu panjang',
                'Topik agenda maksimal 255 karakter.'
            );
        }
        if (strtotime($data['selesai']) <= strtotime($data['mulai'])) {
            popupRedirect(
                'warning',
                'Waktu tidak valid',
                'Waktu selesai harus lebih besar dari waktu mulai.'
            );
        }
        $zoomResult = [
            'success'    => false,
            'join_url'   => '',
            'short_url'  => '',
            'start_url'  => '',
            'meeting_id' => '',
            'password'   => ''
        ];

        $zoomRoomIds = ['D.3.UMKM.004', 'D.3.UMKM.005', 'D.3.UMKM.006'];
        $shouldGenerateZoom = count(array_intersect($zoomRoomIds, $ruangIds)) > 0;

        if ($shouldGenerateZoom && empty($data['linkZoom'])) {
            if ($data['passcode'] !== '' && !preg_match('/^[A-Za-z0-9]{1,10}$/', $data['passcode'])) {
                popupRedirect(
                    'warning',
                    'Passcode tidak valid',
                    'Passcode Zoom maksimal 10 karakter dan hanya boleh huruf/angka.'
                );
            }

            $zoomHostUser = function_exists('getZoomHostByRoom')
                ? getZoomHostByRoom($ruangIds)
                : '';

            $zoom = createZoomMeeting(
                $data['topik'],
                $data['tanggal'],
                $data['mulai'],
                $data['selesai'],
                $data['passcode'],
                $zoomHostUser
            );

            if (empty($zoom['success'])) {
                $pesanZoom = $zoom['message'] ?? 'Gagal membuat link Zoom otomatis.';

                popupRedirect(
                    'error',
                    'Gagal generate Zoom',
                    $pesanZoom
                );
            }

            $data['linkZoom'] = $zoom['join_url'] ?? '';

            if (!empty($zoom['password'])) {
                $data['passcode'] = $zoom['password'];
            }

            $zoomShortUrl = '';

            if (!empty($data['linkZoom']) && function_exists('fs_register_short_link')) {
                try {
                    $short = fs_register_short_link(
                        $conn,
                        'zoom',
                        $data['linkZoom'],
                        [
                            'modul'       => 'agenda',
                            'jenis'       => 'zoom_meeting',
                            'meeting_id'  => (string)($zoom['meeting_id'] ?? ''),
                            'topik'       => $data['topik'],
                            'tanggal'     => $data['tanggal'],
                            'waktu_mulai' => $data['mulai'],
                        ],
                        (string)$nip_pemohon
                    );

                    $zoomShortUrl = $short['short_url'] ?? '';
                } catch (Exception $e) {
                    $zoomShortUrl = '';
                }
            }

            $zoomResult = [
                'success'    => true,
                'join_url'   => $data['linkZoom'],
                'short_url'  => $zoomShortUrl,
                'start_url'  => $zoom['start_url'] ?? '',
                'meeting_id' => $zoom['meeting_id'] ?? '',
                'password'   => $data['passcode']
            ];
        }

        $upload = uploadDokumen(
            $_FILES['file_upload'],
            $ALLOWED_EXTENSIONS,
            $MAX_UPLOAD_SIZE
        );
        if (
            $data['jenis_agenda'] === '1' &&
            !in_array($data['kepentingan'], ['0', '1'], true)
        ) {
            popupRedirect(
                'warning',
                'TV Wall belum dipilih',
                'Pilih apakah agenda ini ingin ditayangkan di TV Wall Lobby.'
            );
        }

        if ($data['jenis_agenda'] === '0') {
            $data['tanggal_selesai'] = $data['tanggal'];
        }
        if (
            $data['jenis_agenda'] === '1' &&
            $data['tanggal_selesai'] === ''
        ) {
            $data['tanggal_selesai'] = $data['tanggal'];
        }
        if (
            $data['tanggal_selesai'] !== '' &&
            strtotime($data['tanggal_selesai']) < strtotime($data['tanggal'])
        ) {
            popupRedirect(
                'warning',
                'Tanggal tidak valid',
                'Tanggal selesai tidak boleh lebih awal.'
            );
        }
        if ($data['jenis_agenda'] === '0') {
            if (empty($ruangIds)) {
                popupRedirect(
                    'warning',
                    'Ruangan belum dipilih',
                    'Silakan pilih ruangan rapat.'
                );
            }
            if (
                $data['meetingType'] === 'Hybrid' &&
                count($ruangIds) > 2
            ) {
                popupRedirect(
                    'warning',
                    'Ruangan terlalu banyak',
                    'Hybrid maksimal 2 ruangan.'
                );
            }
            if (
                $data['meetingType'] !== 'Hybrid' &&
                count($ruangIds) > 1
            ) {
                popupRedirect(
                    'warning',
                    'Ruangan terlalu banyak',
                    'Maksimal 1 ruangan.'
                );
            }

            $namaRuang = [];

            $stmtRuang = $conn->prepare("
                SELECT nama_ruang
                FROM tb_ruangan_rapat
                WHERE id_ruang = ?
                LIMIT 1
            ");

            foreach ($ruangIds as $rid) {
                $stmtRuang->bind_param('s', $rid);
                $stmtRuang->execute();
                $resRuang = $stmtRuang
                    ->get_result()
                    ->fetch_assoc();

                if ($resRuang) {
                    $namaRuang[] = $resRuang['nama_ruang'];
                }
            }

            $id_ruang_str   = implode(',', $ruangIds);
            $nama_ruang_str = implode(', ', $namaRuang);

        } else {

            if (
                $data['meetingType'] === 'Online' ||
                $data['meetingType'] === 'Hybrid Luar Kantor'
            ) {

            if (empty($ruangIds)) {
                popupRedirect(
                    'warning',
                    'Ruangan Zoom belum dipilih',
                    'Silakan pilih ruangan Zoom.'
                );
            }

        $zoom_id = $ruangIds[0];

        $stmtRuang = $conn->prepare("
            SELECT nama_ruang
            FROM tb_ruangan_rapat
            WHERE id_ruang = ?
            AND tipe_ruangan = 0
            LIMIT 1
        ");

        $stmtRuang->bind_param('s', $zoom_id);
        $stmtRuang->execute();

        $resRuang = $stmtRuang->get_result()->fetch_assoc();

        if (!$resRuang) {
            popupRedirect(
                'warning',
                'Ruangan Zoom tidak valid',
                'Ruangan Zoom tidak tersedia.'
            );
        }
        if ($data['meetingType'] === 'Online') {

            $id_ruang_str   = $zoom_id;
            $nama_ruang_str = $resRuang['nama_ruang'];

        } else {
            $id_luar_kantor = 'D.3.UMKM.007';

            $id_ruang_arr = [
                $id_luar_kantor,
                $zoom_id
            ];

            $nama_ruang_arr = [
                $data['nama_tempat'],
                $resRuang['nama_ruang']
            ];

            $id_ruang_str = implode(',', array_filter($id_ruang_arr));
            $nama_ruang_str = implode(', ', array_filter($nama_ruang_arr));
        }
        } else {

            $id_ruang_str   = 'D.3.UMKM.007';
            $nama_ruang_str = $data['nama_tempat'];
        }
        }

        $conn->begin_transaction();

        try {

            if ($data['jenis_agenda'] === '0') {
                foreach ($ruangIds as $rid) {
                    if (
                        cekBentrok(
                            $conn,
                            $rid,
                            $data['tanggal'],
                            $data['tanggal_selesai'],
                            $data['mulai'],
                            $data['selesai']
                        )
                    ) {
                        throw new Exception(
                            "Ruangan sudah digunakan pada jam tersebut."
                        );
                    }
                }
            }
            if (
                $data['jenis_agenda'] === '1' &&
                in_array($data['meetingType'], ['Online', 'Hybrid Luar Kantor'], true)
            ) {
                foreach ($ruangIds as $rid) {
                    if ($rid === 'D.3.UMKM.007') {
                        continue;
                    }

                    if (
                        cekBentrok(
                            $conn,
                            $rid,
                            $data['tanggal'],
                            $data['tanggal_selesai'],
                            $data['mulai'],
                            $data['selesai']
                        )
                    ) {
                        throw new Exception(
                            "Ruangan Zoom sudah digunakan pada jadwal tersebut."
                        );
                    }
                }
            }
            $status = 1;
            $pendamping = implode(', ', $pendampingArr);
            $nama_pelaksana = implode(', ', $pelaksanaArr);

            if ($data['jenis_agenda'] === '0') {
                $id_pertemuan = 1;
                $jenis_kepentingan = 0;
                $kehadiran = '';
                $pendamping = '';
                $nama_pelaksana = '';
                $id_provinsi = $defaultProvinsi['id_provinsi'];
                $nama_provinsi = $defaultProvinsi['nama_provinsi'];
                $id_kabupaten = $defaultKabupaten['id_kabupaten'];
                $nama_kabupaten = $defaultKabupaten['nama_kabupaten'];
                $kode_kota = $defaultKabupaten['kode_kota'];

            } else {
                $id_pertemuan = 2;
                $jenis_kepentingan = $data['kepentingan'];
                $kehadiran = $data['kehadiran'];

                if (
                    $data['meetingType'] === 'Luar Kantor' ||
                    $data['meetingType'] === 'Hybrid Luar Kantor'
                ) {
                    $id_provinsi = $_POST['id_provinsi'] ?? '';
                    $nama_provinsi = $_POST['nama_provinsi'] ?? '';
                    $id_kabupaten = $_POST['id_kabupaten'] ?? '';
                    $nama_kabupaten = $_POST['nama_kabupaten'] ?? '';
                    $kode_kota = $_POST['kode_kota'] ?? '';

                    if ($id_provinsi === '' || $id_kabupaten === '') {
                        popupRedirect(
                            'warning',
                            'Lokasi belum lengkap',
                            'Mohon pilih provinsi dan kabupaten/kota untuk agenda kegiatan luar kantor.'
                        );
                    }
                } else {
                    $id_provinsi = $defaultProvinsi['id_provinsi'];
                    $nama_provinsi = $defaultProvinsi['nama_provinsi'];

                    $id_kabupaten = $defaultKabupaten['id_kabupaten'];
                    $nama_kabupaten = $defaultKabupaten['nama_kabupaten'];
                    $kode_kota = $defaultKabupaten['kode_kota'];
                }
            }
            $zoom_short_url  = $zoomResult['short_url'] ?? '';
            $zoom_meeting_id = $zoomResult['meeting_id'] ?? '';
            $zoom_start_url  = $zoomResult['start_url'] ?? '';

            $stmt = $conn->prepare("
                INSERT INTO tb_agenda_rapat
                ( time_stamp, id_pertemuan, id_ruang_rapat, nama_ruang, topik_rapat, tanggal, tanggal_selesai,
                waktu_mulai, waktu_selesai, penyelenggara, narahubung, passcode, meeting_type, link_zoom,
                zoom_short_url, zoom_meeting_id, zoom_start_url,
                file_upload, ajukan_konsumsi, catatan_konsumsi, kode_satker, nip_pemohon, status,
                jenis_kepentingan, kehadiran, pendamping, keterangan, nama_pelaksana, id_provinsi, nama_provinsi,
                id_kabupaten, nama_kabupaten, kode_kota, jumlah_peserta, kategori_rapat )
                VALUES (
                    NOW(),
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                )
            ");

            $stmt->bind_param(
                'ssssssssssssssssssssssssssssssssss',
                $id_pertemuan,
                $id_ruang_str,
                $nama_ruang_str,
                $data['topik'],
                $data['tanggal'],
                $data['tanggal_selesai'],
                $data['mulai'],
                $data['selesai'],
                $data['penyelenggara'],
                $data['narahubung'],
                $data['passcode'],
                $data['meetingType'],
                $data['linkZoom'],
                $zoom_short_url,
                $zoom_meeting_id,
                $zoom_start_url,
                $upload,
                $data['ajukan'],
                $data['catatan'],
                $kode_satker,
                $nip_pemohon,
                $status,
                $jenis_kepentingan,
                $kehadiran,
                $pendamping,
                $data['keterangan'],
                $nama_pelaksana,
                $id_provinsi,
                $nama_provinsi,
                $id_kabupaten,
                $nama_kabupaten,
                $kode_kota,
                $data['jumlah_peserta'],
                $data['kategori_rapat']
            );
            $stmt->execute();

            $id_agenda = $stmt->insert_id;

            if (!empty($fasilitasArr)) {
                $no_pengajuan = 'BMN-' . date('YmdHis');
                $stmtBmn = $conn->prepare("
                    INSERT INTO pengajuan_bmn
                    (
                        no_pengajuan, id_agenda, nip_pemohon, nip_peminjam, unit_kerja, no_hp,
                        kd_satker, tgl_pinjam, tgl_kembali, lama_hari, keterangan,
                        file_pengajuan, tgl_pengajuan, status
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $nip_peminjam = null;
                $lama_hari = 0;
                $tgl_pengajuan = date('Y-m-d H:i:s');
                $status_bmn = 'Diajukan';

                $stmtBmn->bind_param(
                    "sisssssssissss",
                    $no_pengajuan,
                    $id_agenda,
                    $nip_pemohon,
                    $nip_peminjam,
                    $data['penyelenggara'],
                    $data['narahubung'],
                    $kode_satker,
                    $data['tanggal'],
                    $data['tanggal'],
                    $lama_hari,
                    $data['topik'],
                    $upload,
                    $tgl_pengajuan,
                    $status_bmn
                );
                $stmtBmn->execute();

                $id_pengajuan = $stmtBmn->insert_id;
                foreach ($fasilitasArr as $id_bmn) {
                    $stmtDetail = $conn->prepare("
                        INSERT INTO pengajuan_bmn_detail
                        (
                            id_pengajuan,
                            id_bmn
                        )
                        VALUES (?,?)
                    ");
                    $stmtDetail->bind_param(
                        "is",
                        $id_pengajuan,
                        $id_bmn
                    );
                    $stmtDetail->execute();
                }
            }
            $conn->commit();

            header("Location: /rapat/form_rapat2 copy.php?saved=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            popupRedirect(
                'error',
                'Gagal menyimpan data',
                addslashes($e->getMessage())
            );
        }
    }

    $fasilitas = [];

    $qf = $conn->prepare("
        SELECT id_bmn, spek_bmn
        FROM daftar_bmn
        WHERE is_pinjampakai = 1
        AND kd_satker = ?
        ORDER BY spek_bmn ASC
    ");

    $qf->bind_param("s", $kode_satker);
    $qf->execute();
    $res = $qf->get_result();

    while ($row = $res->fetch_assoc()) {
        $fasilitas[] = $row;
    }

    $penyelenggara = $conn->query("
        SELECT DISTINCT nama
        FROM struktur_organisasi
        WHERE nama IS NOT NULL
        AND nama != ''
        ORDER BY nama DESC
    ");

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

    $provinsi = $conn->query("
        SELECT id_provinsi, nama_provinsi
        FROM tb_provinsi
        ORDER BY nama_provinsi ASC
    ");

    $kabupaten = $conn->query("
        SELECT id_kabupaten, id_provinsi, nama_kabupaten, kode_kota
        FROM tb_kabupaten
        ORDER BY nama_kabupaten ASC
    ");

    $dataKabupaten = [];
    while ($row = $kabupaten->fetch_assoc()) {
        $dataKabupaten[] = $row;
    }
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Formulir Pengajuan Agenda</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <style>
            :root {
                --primary: #1e40af;
                --primary-hover: #1d4ed8;
                --text-dark: #0d3b4c;
                --text: #334155;
                --muted: #64748b;
                --border: #e2e8f0;
                --soft: #eff6ff;
                --danger: #dc2626;
                --bg: #f8fafc;
            }
            body {
                font-family: 'Inter', sans-serif;
                background: var(--bg);
                color: var(--text);
            }
            .col-lg-9 {
                width: 100%;
            }
            .main-card {
                border: 1px solid var(--border);
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
            }
            .card-header {
                background: #fff !important;
                border: 0;
                padding: 1.5rem 2.5rem !important;
                position: relative;
            }
            .card-header::before {
                content: "";
                position: absolute;
                left: 20px;
                top: 22%;
                width: 6px;
                height: 48%;
                background: var(--primary);
            }
            .main-card .card-body {
                padding-top: 0 !important;
            }
            .fw-bold {
                font-size: 1.5rem;
                font-weight: 700;
                letter-spacing: 1.25px;
                color: var(--text-dark);
            }
            .section-title {
                font-size: 1rem;
                font-weight: 700;
                color: var(--primary);
                letter-spacing: .5px;
                padding-bottom: 8px;
                border-bottom: 2px solid var(--soft);
            }
            .form-label {
                font-size: 1rem;
                font-weight: 500;
                color: #475569;
            }
            .custom-input,
            .form-control,
            .form-select,
            .date-compact-display,
            .time-compact-display {
                border: 1.5px solid var(--border);
                border-radius: 10px;
                padding: .6rem 1rem;
                font-size: 1rem;
                transition: .2s ease;
            }
            .custom-input:focus,
            .form-control:focus,
            .form-select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, .1);
                outline: none;
            }
            .no-resize {
                resize: none;
            }
            button:disabled {
                background: #f1f5f9 !important;
                color: #94a3b8 !important;
                cursor: not-allowed;
            }
            .btn-primary {
                background: var(--primary);
                border: 0;
                border-radius: 12px;
                padding: 14px;
                transition: .2s ease;
            }
            .btn-primary:hover {
                background: var(--primary-hover);
                transform: translateY(-2px);
            }
            .dropdown-menu {
                border: 1px solid #e9ecef;
                border-radius: 14px;
                box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08);
                font-size: 1rem;
            }
            .dropdown-item {
                white-space: normal;
            }
            #unit_menu,
            #provinsi_menu,
            #kabupaten_menu,
            #ruangMenu {
                max-height: 280px;
                overflow-y: auto;
                padding: 8px;
            }
            #unit_menu .dropdown-item,
            #ruangMenu .form-check {
                border-radius: 10px;
            }
            #unit_menu .dropdown-item:hover,
            #ruangMenu .form-check:hover,
            .fasilitas-list .form-check:hover {
                background: var(--soft);
            }
            .date-compact-box,
            .time-compact-box {
                cursor: pointer;
            }
            .date-compact-box .input-group-text,
            .time-compact-box .input-group-text {
                border-right: 0;
                background: #fff !important;
                min-height: 48px;
            }
            .date-compact-display,
            .time-compact-display {
                border-left: 0 !important;
                border-radius: 0 10px 10px 0 !important;
                background: #fff;
                min-height: 48px;
                display: flex;
                align-items: center;
                cursor: pointer;
            }
            .date-compact-display.is-empty,
            .time-compact-display.is-empty {
                color: var(--muted);
            }
            .time-compact-box.is-field-invalid .input-group-text,
            .time-compact-box.is-field-invalid .time-compact-display {
                border-color: var(--danger) !important;
                box-shadow: 0 0 0 4px rgba(220, 38, 38, .08) !important;
            }
            .time-hidden {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }
            .soft-box-modern {
                height: 100%;
                background: #fff;
                border: 1.5px solid var(--border);
                border-radius: 14px;
                padding: 18px;
                transition: .2s ease;
            }
            .soft-box-modern:hover {
                border-color: #bfdbfe;
                box-shadow: 0 4px 14px rgba(30, 64, 175, .06);
            }
            .konsumsi-check {
                padding: 12px 14px;
                border-radius: 12px;
                background: var(--bg);
                border: 1.5px solid var(--border);
            }
            .konsumsi-check:hover {
                border-color: #bfdbfe;
            }
            .form-check-input:checked {
                background-color: var(--primary);
                border-color: var(--primary);
            }
            #ruangMenu {
                border: 1.5px solid var(--border);
                box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .10);
            }
            #ruangMenu .dropdown-header {
                font-size: .88rem;
                font-weight: 800;
                color: var(--primary);
                background: var(--soft);
                border-radius: 10px;
                margin: 6px 0 8px;
            }
            #ruangMenu .form-check {
                padding: 0 12px 10px 38px !important;
            }
            #ruangMenu .form-check-input,
            .konsumsi-check .form-check-input {
                margin-left: -24px;
                margin-top: 4px;
                cursor: pointer;
            }
            #dropdownRuang {
                min-height: 48px;
                white-space: normal;
                line-height: 1.4;
            }
            .fasilitas-list {
                max-height: 180px;
                overflow-y: auto;
                padding-right: 4px;
            }
            .fasilitas-list .form-check {
                padding: 9px 12px 9px 2rem;
                border-radius: 10px;
                margin-bottom: 6px;
                background: var(--bg);
            }
            .custom-upload-group {
                border: 1.5px solid var(--border);
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 5px rgba(0, 0, 0, .02);
            }
            .custom-upload-group .form-control,
            .custom-upload-group .input-group-text {
                border: 0;
                background: #fff;
            }
            .custom-upload-group .btn-outline-primary {
                border: 0;
                border-left: 1.5px solid var(--border);
                border-radius: 0;
                background: #f8faff;
                font-size: .9rem;
                letter-spacing: .5px;
            }
            .custom-upload-group:hover {
                border-color: #3b82f6;
            }
            .pegawai-select + .select2-container {
                width: 100% !important;
            }

            .pegawai-select + .select2-container .select2-selection--multiple {
                min-height: 48px !important;
                height: auto !important;
                padding: 6px 10px !important;
                display: flex !important;
                align-items: center !important;
                border: 1.5px solid var(--border) !important;
                border-radius: 10px !important;
            }
            .pegawai-select + .select2-container .select2-selection__rendered {
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                gap: 6px;
                padding: 0 !important;
                margin: 0 !important;
            }
            .pegawai-select + .select2-container .select2-selection__choice {
                position: relative !important;
                display: inline-flex !important;
                align-items: center !important;
                margin: 3px 4px 3px 0 !important;
                padding: 5px 10px 5px 28px !important;
                border-radius: 999px !important;
                background: var(--soft) !important;
                border: 1px solid #bfdbfe !important;
                color: var(--primary) !important;
                font-size: .9rem;
            }
            .pegawai-select + .select2-container .select2-selection__choice__remove {
                position: absolute !important;
                left: 10px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                border: 0 !important;
                color: var(--primary) !important;
                font-weight: 700 !important;
            }
            .select2-dropdown {
                border: 1.5px solid var(--border) !important;
                border-radius: 12px !important;
                overflow: hidden;
                box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08);
            }
            .select2-results__option {
                padding: 10px 14px;
                font-size: .95rem;
            }
            .flatpickr-calendar {
                padding: 14px !important;
                border: 1px solid var(--border) !important;
                border-radius: 18px !important;
                box-shadow: 0 18px 40px rgba(15, 23, 42, .16) !important;
                font-family: 'Inter', sans-serif !important;
            }
            .flatpickr-day {
                border-radius: 12px !important;
                font-weight: 500;
                margin: 2px;
                color: var(--text);
            }
            .flatpickr-day.today {
                border-color: var(--primary) !important;
                color: var(--primary) !important;
                font-weight: 700;
            }
            .flatpickr-day.selected,
            .flatpickr-day.startRange,
            .flatpickr-day.endRange {
                background: var(--primary) !important;
                border-color: var(--primary) !important;
                color: #fff !important;
            }
            .flatpickr-day.inRange {
                background: #dbeafe !important;
                border-color: #dbeafe !important;
            }
            .field-error,
            .error-waktu {
                color: var(--danger);
                font-size: .82rem;
                margin-top: 6px;
                font-weight: 500;
            }
            .is-field-invalid {
                border-color: var(--danger) !important;
                box-shadow: 0 0 0 4px rgba(220, 38, 38, .08) !important;
            }
            .textarea-counter-wrapper {
                position: relative;
            }
            .textarea-with-counter {
                padding-bottom: 34px !important;
            }
            .textarea-counter {
                position: absolute;
                right: 14px;
                bottom: 10px;
                font-size: .78rem;
                color: var(--muted);
                background: rgba(255, 255, 255, .9);
                padding: 2px 6px;
                border-radius: 999px;
                pointer-events: none;
            }
            .save-popup-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .22);
                backdrop-filter: blur(3px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .save-popup-card {
                position: relative;
                width: 360px;
                max-width: 90%;
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 22px;
                padding: 30px 26px 28px;
                text-align: center;
                box-shadow: 0 22px 55px rgba(15, 23, 42, .16);
                animation: popupShow .22s ease;
            }
            .save-popup-close {
                position: absolute;
                top: 14px;
                right: 16px;
                border: 0;
                background: var(--bg);
                color: var(--muted);
                font-size: 22px;
                cursor: pointer;
            }
            .save-popup-icon {
                width: 62px;
                height: 62px;
                margin: 0 auto 16px;
                border-radius: 50%;
                background: var(--soft);
                color: var(--primary);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 34px;
            }
            .save-popup-card h3 {
                margin: 0;
                color: var(--text-dark);
                font-size: 1.18rem;
                font-weight: 700;
            }
            .save-popup-card p {
                margin: 8px 0 0;
                color: var(--muted);
                font-size: .94rem;
                line-height: 1.6;
            }
            @keyframes popupShow {
                from {
                    opacity: 0;
                    transform: translateY(10px) scale(.97);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            /* Tombol CARI FILE sama seperti tombol kirim */
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

            /* Placeholder Select2 agar teks berada di tengah */
            .pegawai-select + .select2-container .select2-selection--multiple {
            align-items: center !important;
            min-height: 48px !important;
            }

            .pegawai-select + .select2-container .select2-search--inline {
            display: flex !important;
            align-items: center !important;
            min-height: 32px !important;
            }

            .pegawai-select + .select2-container .select2-search__field {
            height: 32px !important;
            line-height: 32px !important;
            margin-top: 0 !important;
            padding: 0 !important;
            }
            .flatpickr-calendar {
            min-width: 330px !important;
            z-index: 99999 !important;
            overflow: visible !important;
        }
        .flatpickr-calendar.open {
            max-width: calc(100vw - 24px) !important;
        }
        .flatpickr-innerContainer,
        .flatpickr-rContainer,
        .flatpickr-days,
        .dayContainer {
            min-width: 300px !important;
            max-width: 300px !important;
        }

        .flatpickr-day {
            max-width: 40px !important;
            height: 40px !important;
            line-height: 40px !important;
            margin: 1px !important;
        }
        </style>
    </head>
    <body class="bg-light">
        <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
            <div class="save-popup-overlay" id="savePopup">
                <div class="save-popup-card">
                    <button type="button" class="save-popup-close" onclick="closeSavePopup()">×</button>
                    <div class="save-popup-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3>Pengajuan berhasil disimpan</h3>
                    <p>Data agenda berhasil disimpan.</p>
                </div>
            </div>
        <?php endif; ?>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="card main-card shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h3 class="fw-bold mb-1">AJUKAN AGENDA KEGIATAN</h3>
                            <p class="text-muted">Silakan isi data agenda secara lengkap dan akurat</p>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <form method="post" enctype="multipart/form-data" id="formPengajuanRapat" novalidate>
                                <div class="form-section mb-4">
                                    <h6 class="section-title"><i class="bi bi-info-circle me-2"></i>1. INFORMASI UMUM</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Penyelenggara</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownUnit" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Pilih Penyelenggara
                                                </button>
                                                <ul class="dropdown-menu w-100" id="unit_menu" style="max-height: 280px; overflow-y: auto;">
                                                    <?php while ($row = $penyelenggara->fetch_assoc()): ?>
                                                        <li>
                                                            <a class="dropdown-item unit-option" data-value="<?= htmlspecialchars($row['nama']) ?>">
                                                                <?= htmlspecialchars($row['nama']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endwhile; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item unit-option" data-value="lainnya">
                                                            Lainnya
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="penyelenggara" id="unitSelect">
                                            <div class="mt-3 d-none" id="penyelenggaraLainnyaWrapper">
                                                <label class="form-label">Nama Penyelenggara</label>
                                                <input 
                                                    type="text" 
                                                    class="form-control custom-input" 
                                                    id="penyelenggaraLainnya" 
                                                    placeholder="Masukkan nama penyelenggara"
                                                >
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-block">Jenis Agenda</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownJenisAgenda" data-bs-toggle="dropdown">
                                                    Pilih jenis agenda
                                                </button>
                                                <ul class="dropdown-menu w-100">
                                                    <li><a class="dropdown-item jenis-agenda-option" data-value="0"> Agenda Rapat </a></li>
                                                    <li><a class="dropdown-item jenis-agenda-option" data-value="1"> Agenda Kegiatan </a></li>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="jenis_agenda" id="jenisAgenda">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-block">Kategori Kegiatan/Rapat</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownKategoriAgenda" data-bs-toggle="dropdown" disabled>
                                                    Pilih kategori agenda
                                                </button>
                                                <ul class="dropdown-menu w-100" id="kategoriAgendaMenu">
                                                    <li>
                                                        <span class="dropdown-item text-muted">Pilih jenis agenda terlebih dahulu</span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="kategori_rapat" id="kategoriAgenda">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-section mb-4">
                                    <h6 class="section-title"><i class="bi bi-calendar-event me-2"></i>2. WAKTU & LOKASI</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Tanggal Agenda</label>
                                            <div class="input-group date-compact-box" id="dateBookingBox">
                                                <span class="input-group-text bg-white">
                                                    <i class="bi bi-calendar-event"></i>
                                                </span>
                                                <div class="form-control custom-input date-compact-display is-empty" id="tanggalDisplayText">
                                                    Pilih tanggal
                                                </div>
                                            </div>
                                            <input type="text" id="tanggal_range" class="form-control d-none">
                                            <input type="hidden" name="tanggal" id="tanggal_mulai">
                                            <input type="hidden" name="tanggal_selesai" id="tanggal_selesai">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Mulai</label>
                                            <div class="input-group time-compact-box" data-target="waktu_mulai">
                                                <span class="input-group-text bg-white">
                                                    <i class="bi bi-clock"></i>
                                                </span>
                                                <div class="form-control custom-input time-compact-display is-empty" id="waktuMulaiDisplay">
                                                    Pilih jam mulai
                                                </div>
                                            </div>
                                            <input type="time" id="waktu_mulai" name="waktu_mulai" class="time-hidden" required>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Selesai</label>
                                            <div class="input-group time-compact-box" data-target="waktu_selesai">
                                                <span class="input-group-text bg-white">
                                                    <i class="bi bi-clock-history"></i>
                                                </span>
                                                <div class="form-control custom-input time-compact-display is-empty" id="waktuSelesaiDisplay">
                                                    Pilih jam selesai
                                                </div>
                                            </div>
                                            <input type="time" id="waktu_selesai" name="waktu_selesai" class="time-hidden" required>
                                            <small id="error-waktu" class="error-waktu d-none">Waktu selesai harus lebih dari waktu mulai.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Jenis Pertemuan</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownMeetingType" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                                                    Pilih jenis pertemuan
                                                </button>
                                                <ul class="dropdown-menu w-100" id="meeting_type_menu">
                                                    <li>
                                                        <span class="dropdown-item text-muted">Pilih jenis agenda terlebih dahulu</span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="meeting_type" id="meeting_type">
                                        </div>
                                        <div class="col-md-6 d-none" id="tempatWrapper">
                                            <label class="form-label">Tempat Pertemuan</label>
                                            <input type="text" class="form-control custom-input" name="nama_tempat" id="nama_tempat" placeholder="Masukkan nama tempat pertemuan" required>
                                        </div>
                                        <div class="col-md-6 d-none" id="ruanganWrapper">
                                            <label class="form-label">Nama Ruangan</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownRuang" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Pilih Nama Ruangan
                                                </button>
                                                <ul class="dropdown-menu w-100" id="ruangMenu">
                                                </ul>
                                            </div>
                                            <input type="hidden" id="RuangSelect">
                                        </div>
                                        <div class="col-12 meeting-online d-none">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Link Meeting</label>
                                                    <input type="url"  class="form-control custom-input" id="link_zoom" name="link_zoom" placeholder="Contoh: https://zoom.us/123456789">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Passcode</label>
                                                    <input type="text" class="form-control custom-input" id="passcode" name="passcode" maxlength="6" placeholder="Passcode">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Kapasitas Zoom</label>
                                                    <input type="number" class="form-control custom-input" id="jumlah_peserta" name="jumlah_peserta" min="0" placeholder="Jumlah Peserta Online">
                                                </div>
                                                <div class="form-text mt-1" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Sitem generate otomatis untuk ruang Zoom 1, Zoom 2, dan Zoom 3.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 d-none" id="provinsiWrapper">
                                            <label class="form-label">Provinsi</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownProvinsi" data-bs-toggle="dropdown">
                                                    Pilih Provinsi
                                                </button>
                                                <ul class="dropdown-menu w-100 p-2" id="provinsi_menu" style="max-height: 280px; overflow-y: auto;">
                                                    <li class="mb-2">
                                                        <input type="text" class="form-control custom-input" id="searchProvinsi" placeholder="Cari provinsi...">
                                                    </li>
                                                    <?php while ($row = $provinsi->fetch_assoc()): ?>
                                                        <li>
                                                            <a class="dropdown-item provinsi-option"
                                                            data-id="<?= htmlspecialchars($row['id_provinsi']) ?>"
                                                            data-value="<?= htmlspecialchars($row['nama_provinsi']) ?>">
                                                                <?= htmlspecialchars($row['nama_provinsi']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="id_provinsi" id="id_provinsi">
                                            <input type="hidden" name="nama_provinsi" id="nama_provinsi">
                                        </div>
                                        <div class="col-md-6 d-none" id="kabupatenWrapper">
                                            <label class="form-label">Kabupaten/Kota</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start text-muted custom-input" type="button" id="dropdownKabupaten" data-bs-toggle="dropdown">
                                                    Pilih Kabupaten/Kota
                                                </button>
                                                <ul class="dropdown-menu w-100 p-2" id="kabupaten_menu" style="max-height: 280px; overflow-y: auto;">
                                                    <li class="mb-2">
                                                        <input type="text" class="form-control custom-input" id="searchKabupaten" placeholder="Cari kabupaten/kota...">
                                                    </li>
                                                    <div id="listKabupaten">
                                                        <li>
                                                            <span class="dropdown-item text-muted">Pilih provinsi terlebih dahulu</span>
                                                        </li>
                                                    </div>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="id_kabupaten" id="id_kabupaten">
                                            <input type="hidden" name="nama_kabupaten" id="nama_kabupaten">
                                            <input type="hidden" name="kode_kota" id="kode_kota">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-section mb-4">
                                    <h6 class="section-title"><i class="bi bi-card-text me-2"></i>3. DETAIL AGENDA</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="topik_rapat" class="form-label">Topik Agenda</label>
                                            <div class="textarea-counter-wrapper">
                                                <textarea 
                                                    class="form-control no-resize custom-input textarea-with-counter" 
                                                    name="topik_rapat" 
                                                    id="topik_rapat" 
                                                    rows="3" 
                                                    maxlength="255" 
                                                    placeholder="Tuliskan nama agenda secara singkat" 
                                                    required></textarea>
                                                <small class="textarea-counter">
                                                    <span id="topikCounter">0</span>/255
                                                </small>
                                            </div>
                                            <small id="topikWarning" class="text-danger d-none">
                                                Maksimal 255 karakter.
                                            </small>
                                        </div>
                                        <div id="kehadiran_section" class="col-12 d-none">
                                            <div class="row g-3">
                                                <div class="col-md-12 d-none" id="kehadiran_wrapper">
                                                    <label class="form-label">Kehadiran</label>
                                                    <div class="dropdown w-100">
                                                        <button class="form-select text-start text-muted custom-input" type="button" id="dropdownKehadiran" data-bs-toggle="dropdown">
                                                            Pilih kehadiran
                                                        </button>
                                                        <ul class="dropdown-menu w-100">
                                                            <li><a class="dropdown-item kehadiran-option" data-value="Hadir">Hadir</a></li>
                                                            <li><a class="dropdown-item kehadiran-option" data-value="Diwakilkan">Diwakilkan</a></li>
                                                            <li><a class="dropdown-item kehadiran-option" data-value="Tentatif">Tentatif</a></li>
                                                            <li><a class="dropdown-item kehadiran-option" data-value="Reschedule">Reschedule</a></li>
                                                            <li><a class="dropdown-item kehadiran-option" data-value="Belum Ada Arahan">Belum Ada Arahan</a></li>
                                                        </ul>
                                                    </div>
                                                    <input type="hidden" name="kehadiran" id="kehadiran">
                                                </div>
                                            <div class="col-md-12 d-none" id="pelaksana_group">
                                                <label class="form-label">Nama Pelaksana</label>
                                                <select 
                                                    name="nama_pelaksana[]" 
                                                    id="nama_pelaksana" 
                                                    class="form-control custom-input pegawai-select" 
                                                    multiple
                                                    data-placeholder="Ketik dan pilih nama pelaksana">
                                                    <?php foreach ($pegawaiList as $pegawai): ?>
                                                        <option 
                                                            value="<?= htmlspecialchars($pegawai['nip_pegawai']); ?>"
                                                            data-nama="<?= htmlspecialchars($pegawai['nama_pegawai']); ?>"
                                                        >
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
                                                    class="form-control custom-input pegawai-select" 
                                                    multiple
                                                    data-placeholder="Ketik dan pilih nama pendamping">
                                                    <?php foreach ($pegawaiList as $pegawai): ?>
                                                        <option 
                                                            value="<?= htmlspecialchars($pegawai['nip_pegawai']); ?>"
                                                            data-nama="<?= htmlspecialchars($pegawai['nama_pegawai']); ?>"
                                                        >
                                                            <?= htmlspecialchars($pegawai['nama_pegawai']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12 d-none" id="keteranganWrapper">
                                            <label for="keterangan_rapat" class="form-label">Keterangan</label>
                                            <textarea class="form-control no-resize custom-input" name="keterangan" rows="3" placeholder="Tuliskan keterangan tambahan (opsional)"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-section mb-4">
                                    <h6 class="section-title"><i class="bi bi-person-lines-fill me-2"></i>4. KONTAK & LAINNYA</h6>
                                    <div class="row g-3">
                                        <div class="col-12 d-none" id="tvWallSection">
                                            <div class="soft-box-modern">
                                                <label class="form-label mb-1">
                                                    Penayangan TV Wall Lobby
                                                </label>

                                                <div class="form-text mb-3">
                                                    Apakah agenda ini ingin ditayangkan di TV Wall Lobby?
                                                </div>

                                                <div class="d-flex gap-3 flex-wrap">
                                                    <div class="form-check konsumsi-check">
                                                        <input 
                                                            class="form-check-input tvwall-option" 
                                                            type="checkbox" 
                                                            name="kepentingan_pilihan"
                                                            id="tvwall_ya" 
                                                            value="0"
                                                        >
                                                        <label class="form-check-label fw-semibold" for="tvwall_ya">
                                                            Ya, tampilkan
                                                        </label>
                                                    </div>

                                                    <div class="form-check konsumsi-check">
                                                        <input 
                                                            class="form-check-input tvwall-option" 
                                                            type="checkbox" 
                                                            name="kepentingan_pilihan"
                                                            id="tvwall_tidak" 
                                                            value="1"
                                                        >
                                                        <label class="form-check-label fw-semibold" for="tvwall_tidak">
                                                            Tidak
                                                        </label>
                                                    </div>
                                                </div>

                                                <input type="hidden" name="kepentingan" id="kepentingan">

                                                <small id="kepentinganError" class="text-danger d-none">
                                                    Pilih salah satu: Ya atau Tidak.
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-12 d-none" id="fasilitasKonsumsiSection">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="soft-box-modern">
                                                        <label class="form-label mb-1">Tambahan Fasilitas</label>
                                                        <div class="form-text mb-3">
                                                            Centang fasilitas tambahan yang dibutuhkan.
                                                        </div>
                                                        <div id="fasilitasContainer" class="fasilitas-list">
                                                            <?php if (!empty($fasilitas)): ?>
                                                                <?php foreach ($fasilitas as $f): ?>
                                                                    <div class="form-check">
                                                                        <input 
                                                                            class="form-check-input" 
                                                                            type="checkbox" 
                                                                            name="fasilitas[]" 
                                                                            value="<?= htmlspecialchars($f['id_bmn']); ?>" 
                                                                            id="fasilitas_<?= htmlspecialchars($f['id_bmn']); ?>"
                                                                        >
                                                                        <label 
                                                                            class="form-check-label" 
                                                                            for="fasilitas_<?= htmlspecialchars($f['id_bmn']); ?>"
                                                                        >
                                                                            <?= htmlspecialchars($f['spek_bmn']); ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <div class="text-muted">
                                                                    Tidak ada fasilitas tambahan tersedia.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="soft-box-modern">
                                                        <div class="form-check konsumsi-check mb-3">
                                                            <input 
                                                                class="form-check-input" 
                                                                type="checkbox" 
                                                                id="ajukan_konsumsi" 
                                                                name="ajukan_konsumsi"
                                                            >
                                                            <label class="form-check-label fw-semibold" for="ajukan_konsumsi">
                                                                Ajukan Konsumsi
                                                            </label>
                                                        </div>
                                                        <textarea 
                                                            id="catatan_konsumsi" 
                                                            name="catatan_konsumsi" 
                                                            class="form-control no-resize custom-input d-none" 
                                                            rows="5" 
                                                            placeholder="Tuliskan detail konsumsi, jumlah peserta, atau catatan tambahan"
                                                        ></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Narahubung (WA)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-whatsapp"></i></span>
                                                <input type="text" class="form-control custom-input border-start-0" name="narahubung" id="narahubung" placeholder="62xxxxxxxxxxx">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Upload Dokumen Pendukung</label>
                                            <div class="input-group custom-upload-group">
                                                <span class="input-group-text bg-white border-end-0 text-muted">
                                                    <i class="bi bi-file-earmark-arrow-up"></i>
                                                </span>
                                                <span id="fileLabel" class="form-control border-start-0 border-end-0 text-muted d-flex align-items-center">
                                                    Pilih file dokumen pendukung
                                                </span>
                                                <input type="file" class="form-control d-none" id="fileUpload" name="file_upload">
                                                <button class="btn btn-outline-primary fw-medium px-4" type="button" onclick="document.getElementById('fileUpload').click()">
                                                    CARI FILE
                                                </button>
                                            </div>
                                            <div class="form-text mt-2" style="font-size: 0.85rem;">
                                                <i class="bi bi-info-circle me-1"></i> Format: PDF, JPG, atau PNG (Maks. 5MB)
                                            </div>
                                            <div id="fileUploadError" class="field-error d-none"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg fw-semibold shadow-sm fs-5">
                                        <i class="bi bi-send-fill me-2"></i>SIMPAN PENGAJUAN AGENDA
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        const dataKabupaten = <?= json_encode($dataKabupaten); ?>;
            let kalenderAgenda = null;
            let selectedRooms = [];

            $(function () {
                const form = $('#formPengajuanRapat');

                initSelect2();
                initUpload();
                initPenyelenggara();
                initJenisAgenda();
                initKategoriAgenda();
                initKehadiran();
                initMeetingType();
                initProvinsiKabupaten();
                initKonsumsi();
                initTanggal();
                initWaktu();
                initTopikCounter();
                initValidation();
                initSavePopup();
            });

            function escapeHtml(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatTanggalIndo(dateStr) {
                if (!dateStr) return 'Pilih tanggal';

                return new Date(dateStr + 'T00:00:00').toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
            }

            function clearDropdownError(buttonSelector) {
                $(buttonSelector).removeClass('is-field-invalid');
                $(buttonSelector).closest('.dropdown').next('.field-error').remove();
            }

            function showDropdownError(buttonSelector, message) {
                const el = $(buttonSelector);
                el.addClass('is-field-invalid');

                const parent = el.closest('.dropdown');
                if (parent.next('.field-error').length === 0) {
                    parent.after(`<div class="field-error">${message}</div>`);
                }
            }

            function showFieldError(selector, message) {
                const el = $(selector);
                el.addClass('is-field-invalid');

                const target = el.closest('.input-group').length ? el.closest('.input-group') : el;

                if (target.next('.field-error').length === 0) {
                    target.after(`<div class="field-error">${message}</div>`);
                }
            }

            function clearFieldErrors() {
                $('.field-error').not('#fileUploadError').remove();
                $('.is-field-invalid').removeClass('is-field-invalid');
                $('#fileUploadError').addClass('d-none').text('');
            }

            function clearErrorByElement(el) {
                const $el = $(el);

                $el.removeClass('is-field-invalid is-invalid');
                $el.next('.field-error').remove();
                $el.closest('.input-group').next('.field-error').remove();
                $el.closest('.dropdown').next('.field-error').remove();
            }

            function initSelect2() {
                $('.pegawai-select').select2({
                    width: '100%',
                    placeholder: function () {
                        return $(this).data('placeholder');
                    },
                    allowClear: true,
                    closeOnSelect: false,
                    dropdownParent: $('#formPengajuanRapat')
                });
            }

            function initUpload() {
                $('#fileUpload').on('change', function () {
                    const file = this.files[0];
                    const allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
                    const maxSize = 5 * 1024 * 1024;

                    $('#fileUploadError').addClass('d-none').text('');
                    $('.custom-upload-group').removeClass('is-field-invalid');

                    if (!file) {
                        $('#fileLabel').text('Pilih file dokumen pendukung');
                        return;
                    }

                    const ext = file.name.split('.').pop().toLowerCase();

                    if (!allowed.includes(ext)) {
                        this.value = '';
                        $('#fileLabel').text('Pilih file dokumen pendukung');
                        $('.custom-upload-group').addClass('is-field-invalid');
                        $('#fileUploadError')
                            .removeClass('d-none')
                            .text('Format file tidak sesuai. Gunakan PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, atau PNG.');
                        return;
                    }

                    if (file.size > maxSize) {
                        this.value = '';
                        $('#fileLabel').text('Pilih file dokumen pendukung');
                        $('.custom-upload-group').addClass('is-field-invalid');
                        $('#fileUploadError')
                            .removeClass('d-none')
                            .text('Ukuran file terlalu besar. Maksimal 5MB.');
                        return;
                    }

                    $('#fileLabel').text(file.name);
                });
            }

            function initPenyelenggara() {
                $('.unit-option').on('click', function () {
                    const value = $(this).data('value');
                    const text = $(this).text().trim();

                    $('#dropdownUnit').text(text).removeClass('text-muted is-empty');
                    clearDropdownError('#dropdownUnit');

                    if (value === 'lainnya') {
                        $('#unitSelect').val('');
                        $('#penyelenggaraLainnyaWrapper').removeClass('d-none');
                        $('#penyelenggaraLainnya').val('').focus();
                    } else {
                        $('#penyelenggaraLainnyaWrapper').addClass('d-none');
                        $('#penyelenggaraLainnya').val('');
                        $('#unitSelect').val(value).trigger('change');
                    }
                });

                $('#penyelenggaraLainnya').on('input', function () {
                    $('#unitSelect').val($(this).val().trim());
                    clearDropdownError('#dropdownUnit');
                });
            }

            function initJenisAgenda() {
                const kategoriByJenis = {
                    0: ['Rapat DPRD', 'Rapat Internal', 'Rapat MoU', 'Dinas/KL/Stakeholder'],
                    1: ['Forum Guest Discussion (FGD)', 'Bimbingan Teknis', 'Bimbingan dan Sosialisasi', 'Pelatihan', 'Rapat Luar Kantor', 'Lainnya']
                };

                $('.jenis-agenda-option').on('click', function () {
                    const value = String($(this).data('value'));
                    const text = value === '0' ? 'Agenda Rapat' : 'Agenda Kegiatan';

                    $('#jenisAgenda').val(value);
                    $('#dropdownJenisAgenda').text(text).removeClass('text-muted is-empty');
                    clearDropdownError('#dropdownJenisAgenda');

                    resetKategoriDanPertemuan();
                    $('#dropdownKategoriAgenda, #dropdownMeetingType').prop('disabled', false);

                    if (value === '0') {
                        setupAgendaRapat();
                    } else {
                        setupAgendaKegiatan();
                    }

                    renderKategori(kategoriByJenis[value]);
                    $('.meeting-online').addClass('d-none');
                });
            }

            function resetKategoriDanPertemuan() {
                $('#kategoriAgenda').val('');
                $('#dropdownKategoriAgenda').text('Pilih kategori agenda').addClass('text-muted');

                $('#meeting_type').val('');
                $('#dropdownMeetingType').text('Pilih jenis pertemuan').addClass('text-muted');

                $('#meeting_type_menu').html('');
            }

            function setupAgendaRapat() {
                initKalenderAgenda('rapat');

                $('#meeting_type_menu').html(`
                    <li><a class="dropdown-item meeting-type-option" data-value="Hybrid">Hybrid</a></li>
                    <li><a class="dropdown-item meeting-type-option" data-value="Online">Online</a></li>
                    <li><a class="dropdown-item meeting-type-option" data-value="Offline">Offline</a></li>
                `);

                $('#ruanganWrapper').removeClass('d-none');
                $('#tempatWrapper, #keteranganWrapper, #provinsiWrapper, #kabupatenWrapper, #kehadiran_section, #tvWallSection').addClass('d-none');

                $('#nama_tempat').val('').prop('required', false);
                $('textarea[name="keterangan"]').val('');

                $('#kepentingan').val('0');
                $('.tvwall-option').prop('checked', false);
                $('#kepentinganError').addClass('d-none');

                resetKehadiran();
                $('#fasilitasKonsumsiSection').removeClass('d-none');

                $('#dropdownProvinsi').text('DKI Jakarta').removeClass('is-empty text-muted');
                $('#dropdownKabupaten').text('Kota Adm. Jakarta Selatan').removeClass('is-empty text-muted');
            }

            function setupAgendaKegiatan() {
                initKalenderAgenda('kegiatan');

                $('#meeting_type_menu').html(`
                    <li><a class="dropdown-item meeting-type-option" data-value="Hybrid Luar Kantor">Hybrid Luar Kantor</a></li>
                    <li><a class="dropdown-item meeting-type-option" data-value="Luar Kantor">Luar Kantor</a></li>
                    <li><a class="dropdown-item meeting-type-option" data-value="Online">Online</a></li>
                `);

                $('#ruanganWrapper, #provinsiWrapper, #kabupatenWrapper').addClass('d-none');
                $('#tempatWrapper, #keteranganWrapper, #kehadiran_section, #kehadiran_wrapper, #tvWallSection').removeClass('d-none');

                $('#dropdownRuang').text('Pilih Nama Ruangan').addClass('text-muted');
                $('#RuangSelect').val('');

                $('#nama_tempat').prop('required', true);

                $('#kepentingan').val('');
                $('.tvwall-option').prop('checked', false);
                $('#kepentinganError').addClass('d-none');

                resetKehadiran();

                $('#id_provinsi').val('31');
                $('#nama_provinsi').val('DKI Jakarta');
                $('#id_kabupaten').val('3174');
                $('#nama_kabupaten').val('Kota Adm. Jakarta Selatan');
                $('#kode_kota').val('1');

                $('#dropdownProvinsi').text('DKI Jakarta').removeClass('text-muted');
                $('#dropdownKabupaten').text('Kota Adm. Jakarta Selatan').removeClass('text-muted');

                $('#fasilitasKonsumsiSection').addClass('d-none');
                $('input[name="fasilitas[]"]').prop('checked', false);
                $('#ajukan_konsumsi').prop('checked', false);
                $('#catatan_konsumsi').addClass('d-none').val('');
            }

            function renderKategori(list) {
                let html = '';
                list.forEach(kategori => {
                    html += `<li><a class="dropdown-item kategori-agenda-option" data-value="${kategori}">${kategori}</a></li>`;
                });
                $('#kategoriAgendaMenu').html(html);
            }

            function initKategoriAgenda() {
                $(document).on('click', '.kategori-agenda-option', function () {
                    const value = $(this).attr('data-value');

                    $('#kategoriAgenda').val(value);
                    $('#dropdownKategoriAgenda').text(value).removeClass('text-muted is-empty');
                    clearDropdownError('#dropdownKategoriAgenda');
                });
            }

            function initKehadiran() {
                $('.kehadiran-option').on('click', function () {
                    const value = $(this).data('value');

                    $('#kehadiran').val(value);
                    $('#dropdownKehadiran').text(value).removeClass('text-muted is-empty');

                    if (value === 'Hadir' || value === 'Diwakilkan') {
                        $('#pelaksana_group, #pendamping_group').removeClass('d-none');
                    } else {
                        resetTambahanField();
                    }
                });
            }

            function resetKehadiran() {
                $('#kehadiran').val('');
                $('#dropdownKehadiran').text('Pilih kehadiran').addClass('text-muted');
                resetTambahanField();
            }

            function resetTambahanField() {
                $('#pendamping_group, #pelaksana_group').addClass('d-none');
                $('#nama_pelaksana, #pendamping').val(null).trigger('change');
            }

            function initMeetingType() {
                $(document).on('click', '.meeting-type-option', function () {
                    const value = $(this).data('value');
                    const jenisAgenda = $('#jenisAgenda').val();

                    $('#dropdownMeetingType').text(value).removeClass('text-muted is-empty');
                    $('#meeting_type').val(value).trigger('change');
                    clearDropdownError('#dropdownMeetingType');

                    $('.meeting-online').toggleClass(
                        'd-none',
                        !(value === 'Online' || value === 'Hybrid' || value === 'Hybrid Luar Kantor')
                    );

                    if (jenisAgenda === '0') {
                        $('#ruanganWrapper').removeClass('d-none');
                        loadAvailableRoomsRapat();
                        return;
                    }

                    if (jenisAgenda === '1') {
                        setupMeetingKegiatan(value);
                    }
                });
            }

            function setupMeetingKegiatan(value) {
                if (value === 'Luar Kantor') {
                    $('#tempatWrapper, #provinsiWrapper, #kabupatenWrapper').removeClass('d-none');
                    $('#ruanganWrapper, .meeting-online').addClass('d-none');
                    resetLokasiLuarKantor();
                    resetRuang();
                }

                if (value === 'Hybrid Luar Kantor') {
                    $('#tempatWrapper, #ruanganWrapper, .meeting-online, #provinsiWrapper, #kabupatenWrapper').removeClass('d-none');
                    resetLokasiLuarKantor();
                    loadZoomRoomsForKegiatan();
                }

                if (value === 'Online') {
                    $('#tempatWrapper, #provinsiWrapper, #kabupatenWrapper').addClass('d-none');
                    $('#ruanganWrapper, .meeting-online').removeClass('d-none');

                    $('#nama_tempat').val('').prop('required', false);
                    setDefaultJakarta();
                    loadZoomRoomsForKegiatan();
                }

                if (value === 'Luar Kantor' || value === 'Hybrid Luar Kantor') {
                    $('#nama_tempat').prop('required', true);
                }
            }

            function resetLokasiLuarKantor() {
                $('#dropdownProvinsi').text('Pilih Provinsi').addClass('text-muted');
                $('#dropdownKabupaten').text('Pilih Kabupaten/Kota').addClass('text-muted');
                $('#id_provinsi, #nama_provinsi, #id_kabupaten, #nama_kabupaten, #kode_kota').val('');
            }

            function setDefaultJakarta() {
                $('#id_provinsi').val('31');
                $('#nama_provinsi').val('DKI Jakarta');
                $('#id_kabupaten').val('3174');
                $('#nama_kabupaten').val('Kota Adm. Jakarta Selatan');
                $('#kode_kota').val('1');
            }

            function resetRuang() {
                $('#dropdownRuang').prop('disabled', true).text('Pilih Nama Ruangan').addClass('text-muted');
                $('#ruangMenu').empty();
                selectedRooms = [];
                $('#formPengajuanRapat').find('input[name="id_ruang[]"]').remove();
            }

            function updateRuangDropdown() {
                const form = $('#formPengajuanRapat');

                if (selectedRooms.length === 0) {
                    $('#dropdownRuang').text('Pilih Nama Ruangan').addClass('text-muted');
                } else {
                    $('#dropdownRuang').text(selectedRooms.map(r => r.nama).join(', ')).removeClass('text-muted is-empty');
                }

                form.find('input[name="id_ruang[]"]').remove();

                selectedRooms.forEach(r => {
                    $('<input>', {
                        type: 'hidden',
                        name: 'id_ruang[]',
                        value: r.id
                    }).appendTo(form);
                });
            }

            function loadAvailableRoomsRapat() {
                const meetingType = $('#meeting_type').val();
                const tanggal = $('#tanggal_mulai').val();
                const mulai = $('#waktu_mulai').val();
                const selesai = $('#waktu_selesai').val();

                if ($('#jenisAgenda').val() !== '0') return;

                loadRooms({
                    meetingType,
                    tanggal,
                    tanggalSelesai: tanggal,
                    mulai,
                    selesai,
                    emptyText: 'Tidak ada ruangan tersedia.',
                    loadingText: 'Memuat ruangan...',
                    defaultText: 'Pilih Nama Ruangan',
                    type: 'rapat'
                });
            }

            function loadZoomRoomsForKegiatan() {
                const tanggal = $('#tanggal_mulai').val();
                const tanggalSelesai = $('#tanggal_selesai').val() || tanggal;
                const mulai = $('#waktu_mulai').val();
                const selesai = $('#waktu_selesai').val();

                loadRooms({
                    meetingType: 'Online',
                    tanggal,
                    tanggalSelesai,
                    mulai,
                    selesai,
                    emptyText: 'Tidak ada ruangan Zoom tersedia pada jadwal tersebut.',
                    loadingText: 'Memuat ruangan Zoom...',
                    defaultText: 'Pilih Ruangan Zoom',
                    type: 'zoom'
                });
            }

            function loadRooms({ meetingType, tanggal, tanggalSelesai, mulai, selesai, emptyText, loadingText, defaultText, type }) {
                const dropdown = $('#dropdownRuang');
                const menu = $('#ruangMenu');
                const form = $('#formPengajuanRapat');

                menu.empty();
                selectedRooms = [];
                form.find('input[name="id_ruang[]"]').remove();

                if (!meetingType || !tanggal || !mulai || !selesai) {
                    dropdown.prop('disabled', true).text('Lengkapi tanggal dan waktu').addClass('text-muted');
                    menu.html('<div class="text-muted px-2 py-1">Isi tanggal, waktu mulai, dan waktu selesai terlebih dahulu.</div>');
                    return;
                }

                dropdown.prop('disabled', true).text(loadingText).addClass('text-muted');

                fetch(`?action=get_available_rooms&meeting_type=${encodeURIComponent(meetingType)}&tanggal=${encodeURIComponent(tanggal)}&tanggal_selesai=${encodeURIComponent(tanggalSelesai || tanggal)}&mulai=${encodeURIComponent(mulai)}&selesai=${encodeURIComponent(selesai)}`)
                    .then(res => res.json())
                    .then(data => {
                        menu.empty();

                        if (!data || Object.keys(data).length === 0) {
                            dropdown.prop('disabled', false).text('Tidak ada ruangan tersedia').addClass('text-muted');
                            menu.html(`<div class="text-muted px-2 py-1">${emptyText}</div>`);
                            return;
                        }

                        Object.keys(data).forEach(group => {
                            menu.append(`<h6 class="dropdown-header">${escapeHtml(group)}</h6>`);

                            data[group].forEach(r => {
                                const inputClass = type === 'zoom' ? 'zoom-room-check' : 'ruang-check';
                                const inputType = type === 'zoom' ? 'radio' : 'checkbox';
                                const inputName = type === 'zoom' ? 'name="zoom_room_temp"' : '';

                                menu.append(`
                                    <div class="form-check px-3 py-1">
                                        <input class="form-check-input ${inputClass}" ${inputName}
                                            type="${inputType}"
                                            value="${escapeHtml(r.id_ruang)}"
                                            data-nama="${escapeHtml(r.nama_ruang)}"
                                            id="${type}_${escapeHtml(r.id_ruang)}">
                                        <label class="form-check-label" for="${type}_${escapeHtml(r.id_ruang)}">
                                            ${escapeHtml(r.nama_ruang)}
                                        </label>
                                    </div>
                                `);
                            });
                        });

                        dropdown.prop('disabled', false).text(defaultText).removeClass('text-muted');
                    });
            }

            $(document).on('change', '.ruang-check', function () {
                const type = $('#meeting_type').val();
                const maxSelect = type === 'Hybrid' ? 2 : 1;

                if (this.checked) {
                    if (selectedRooms.length >= maxSelect) {
                        this.checked = false;
                        alert(type === 'Hybrid' ? 'Hybrid maksimal 2 ruangan' : 'Hanya boleh pilih 1 ruangan');
                        return;
                    }

                    selectedRooms.push({
                        id: this.value,
                        nama: $(this).data('nama')
                    });
                } else {
                    selectedRooms = selectedRooms.filter(r => r.id !== this.value);
                }

                updateRuangDropdown();
                clearDropdownError('#dropdownRuang');
            });

            $(document).on('change', '.zoom-room-check', function () {
                const form = $('#formPengajuanRapat');

                $('#dropdownRuang').text($(this).data('nama')).removeClass('text-muted');
                form.find('input[name="id_ruang[]"]').remove();

                $('<input>', {
                    type: 'hidden',
                    name: 'id_ruang[]',
                    value: this.value
                }).appendTo(form);

                clearDropdownError('#dropdownRuang');
            });

            function initProvinsiKabupaten() {
                $('#searchProvinsi, #searchKabupaten').on('click', e => e.stopPropagation());

                $('#searchProvinsi').on('keyup', function () {
                    filterDropdown('#provinsi_menu', $(this).val());
                });

                $('.provinsi-option').on('click', function () {
                    const idProvinsi = $(this).data('id');
                    const namaProvinsi = $(this).data('value');

                    $('#dropdownProvinsi').text(namaProvinsi).removeClass('text-muted is-empty');
                    $('#id_provinsi').val(idProvinsi);
                    $('#nama_provinsi').val(namaProvinsi);

                    $('#dropdownKabupaten').text('Pilih Kabupaten/Kota').addClass('text-muted');
                    $('#id_kabupaten, #nama_kabupaten, #kode_kota, #searchKabupaten').val('');

                    renderKabupaten(idProvinsi);
                    clearDropdownError('#dropdownProvinsi');
                });

                $(document).on('keyup', '#searchKabupaten', function () {
                    filterDropdown('#kabupaten_menu', $(this).val(), '.kabupaten-option');
                });

                $(document).on('click', '#searchKabupaten', e => e.stopPropagation());

                $(document).on('click', '.kabupaten-option', function () {
                    $('#dropdownKabupaten').text($(this).data('value')).removeClass('text-muted is-empty');
                    $('#id_kabupaten').val($(this).data('id'));
                    $('#nama_kabupaten').val($(this).data('value'));
                    $('#kode_kota').val($(this).data('kode'));

                    clearDropdownError('#dropdownKabupaten');
                });
            }

            function filterDropdown(menuSelector, keyword, itemSelector = '.dropdown-item') {
                keyword = String(keyword || '').toLowerCase();

                $(`${menuSelector} ${itemSelector}`).each(function () {
                    $(this).closest('li').toggle($(this).text().toLowerCase().includes(keyword));
                });
            }

            function renderKabupaten(idProvinsi) {
                const filtered = dataKabupaten.filter(item => item.id_provinsi == idProvinsi);

                let html = `
                    <li class="mb-2">
                        <input type="text" class="form-control custom-input" id="searchKabupaten" placeholder="Cari kabupaten/kota...">
                    </li>
                `;

                if (filtered.length) {
                    filtered.forEach(item => {
                        html += `
                            <li>
                                <a class="dropdown-item kabupaten-option"
                                    data-id="${item.id_kabupaten}"
                                    data-value="${item.nama_kabupaten}"
                                    data-kode="${item.kode_kota}">
                                    ${item.nama_kabupaten}
                                </a>
                            </li>
                        `;
                    });
                } else {
                    html += `<li><span class="dropdown-item text-muted">Kabupaten tidak tersedia</span></li>`;
                }

                $('#kabupaten_menu').html(html);
            }
            function initKonsumsi() {
                $('#ajukan_konsumsi').on('change', function () {
                    if (this.checked) {
                        $('#catatan_konsumsi').removeClass('d-none').hide().slideDown(200);
                    } else {
                        $('#catatan_konsumsi').slideUp(200, function () {
                            $(this).addClass('d-none').val('');
                        });
                    }
                });

                $('.tvwall-option').on('change', function () {
                    $('.tvwall-option').not(this).prop('checked', false);

                    $('#kepentingan').val(this.checked ? this.value : '');
                    $('#kepentinganError').addClass('d-none').text('');
                });
            }

            function initTanggal() {
                $('#dateBookingBox').on('click', function () {
                    $('#tanggalDisplayText').removeClass('is-field-invalid');
                    $('#dateBookingBox').next('.field-error').remove();

                    if (!kalenderAgenda) {
                        $('#tanggalDisplayText').addClass('is-field-invalid');
                        $('#dateBookingBox').after('<div class="field-error">Pilih jenis agenda terlebih dahulu sebelum memilih tanggal.</div>');
                        showDropdownError('#dropdownJenisAgenda', 'Jenis agenda wajib dipilih terlebih dahulu.');
                        return;
                    }

                    kalenderAgenda.open();
                });
            }

            function initKalenderAgenda(mode) {
                if (kalenderAgenda) kalenderAgenda.destroy();

                $('#tanggal_mulai, #tanggal_selesai').val('');
                $('#tanggalDisplayText').text('Pilih tanggal').addClass('is-empty');

                kalenderAgenda = flatpickr('#tanggal_range', {
                    locale: 'id',
                    dateFormat: 'Y-m-d',
                    mode: mode === 'rapat' ? 'single' : 'range',
                    minDate: 'today',
                    showMonths: 1,
                    disableMobile: true,
                    appendTo: document.body,
                    positionElement: document.getElementById('dateBookingBox'),
                    position: 'auto',
                    onChange: function (selectedDates, dateStr) {
                        if (mode === 'rapat') {
                            setTanggal(dateStr, dateStr, formatTanggalIndo(dateStr));
                            loadAvailableRoomsRapat();
                            return;
                        }

                        if (selectedDates.length === 1) {
                            const mulai = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                            setTanggal(mulai, '', formatTanggalIndo(mulai));
                        }

                        if (selectedDates.length === 2) {
                            const mulai = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                            const selesai = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                            setTanggal(mulai, selesai, `${formatTanggalIndo(mulai)} - ${formatTanggalIndo(selesai)}`);
                        }
                    }
                });
            }

            function setTanggal(mulai, selesai, label) {
                $('#tanggal_mulai').val(mulai).trigger('change');
                $('#tanggal_selesai').val(selesai);
                $('#tanggalDisplayText').text(label).removeClass('is-empty is-field-invalid');
                $('#dateBookingBox').next('.field-error').remove();
            }

            function initWaktu() {
                $('.time-compact-box').on('click', function () {
                    const target = $(this).data('target');
                    document.getElementById(target).showPicker?.();
                    document.getElementById(target).click();
                });

                $('#waktu_mulai').on('change', function () {
                    updateTimeDisplay('#waktuMulaiDisplay', this.value, 'Pilih jam mulai', 'waktu_mulai');
                    validateTime();
                    reloadRoomsByCondition();
                });

                $('#waktu_selesai').on('change', function () {
                    updateTimeDisplay('#waktuSelesaiDisplay', this.value, 'Pilih jam selesai', 'waktu_selesai');
                    validateTime();
                    reloadRoomsByCondition();
                });
            }

            function updateTimeDisplay(displaySelector, value, placeholder, target) {
                $(displaySelector)
                    .text(value || placeholder)
                    .toggleClass('is-empty', !value)
                    .removeClass('is-field-invalid');

                $(`.time-compact-box[data-target="${target}"]`)
                    .removeClass('is-field-invalid')
                    .next('.field-error')
                    .remove();
            }

            function validateTime() {
                const mulai = $('#waktu_mulai').val();
                const selesai = $('#waktu_selesai').val();

                if (!mulai || !selesai) return true;

                const valid = selesai > mulai;

                $('#error-waktu').toggleClass('d-none', valid);
                $('#waktu_selesai').toggleClass('is-invalid', !valid);
                $('#waktuSelesaiDisplay').toggleClass('is-field-invalid', !valid);
                $('.time-compact-box[data-target="waktu_selesai"]').toggleClass('is-field-invalid', !valid);

                return valid;
            }

            function reloadRoomsByCondition() {
                const jenisAgenda = $('#jenisAgenda').val();
                const meetingType = $('#meeting_type').val();

                if (jenisAgenda === '0') {
                    loadAvailableRoomsRapat();
                }

                if (jenisAgenda === '1' && (meetingType === 'Online' || meetingType === 'Hybrid Luar Kantor')) {
                    loadZoomRoomsForKegiatan();
                }
            }

            function initTopikCounter() {
                $('#topik_rapat').on('input', function () {
                    const length = this.value.length;

                    $('#topikCounter').text(length);
                    $('#topikWarning').toggleClass('d-none', length < 255);
                    $(this).toggleClass('is-invalid', length >= 255);

                    clearErrorByElement(this);
                });
            }

            function initValidation() {
                $('#narahubung').on('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    clearErrorByElement(this);

                    if (this.value && !this.value.startsWith('62')) {
                        showFieldError('#narahubung', 'Nomor WhatsApp harus diawali dengan 62.');
                    }
                });

                $('#nama_tempat').on('input change', function () {
                    clearErrorByElement(this);
                });

                $(document).on('click', '.meeting-type-option', () => clearDropdownError('#dropdownMeetingType'));
                $(document).on('click', '.jenis-agenda-option', () => clearDropdownError('#dropdownJenisAgenda'));
                $(document).on('click', '.kategori-agenda-option', () => clearDropdownError('#dropdownKategoriAgenda'));

                $('#formPengajuanRapat').on('submit', function (e) {
                    clearFieldErrors();

                    let valid = true;

                    valid = validateRequiredFields() && valid;
                    valid = validateUpload() && valid;
                    valid = validateTime() && valid;
                    valid = validateAgendaRules() && valid;

                    if (!valid) {
                        e.preventDefault();

                        const firstError = $('.field-error:first, #kepentinganError:not(.d-none):first');

                        if (firstError.length) {
                            $('html, body').animate({
                                scrollTop: firstError.offset().top - 140
                            }, 300);
                        }

                        return false;
                    }
                });
            }

            function validateRequiredFields() {
                let valid = true;

                const fields = [
                    ['#dropdownUnit', $('#unitSelect').val(), 'Penyelenggara wajib diisi.', 'dropdown'],
                    ['#dropdownJenisAgenda', $('#jenisAgenda').val(), 'Jenis agenda wajib dipilih.', 'dropdown'],
                    ['#dropdownKategoriAgenda', $('#kategoriAgenda').val(), 'Kategori agenda wajib dipilih.', 'dropdown'],
                    ['#dropdownMeetingType', $('#meeting_type').val(), 'Jenis pertemuan wajib dipilih.', 'dropdown'],
                    ['#topik_rapat', $('#topik_rapat').val().trim(), 'Topik agenda wajib diisi.', 'field'],
                    ['#narahubung', $('#narahubung').val().trim(), 'Narahubung wajib diisi.', 'field']
                ];

                fields.forEach(([selector, value, message, type]) => {
                    if (!value) {
                        type === 'dropdown' ? showDropdownError(selector, message) : showFieldError(selector, message);
                        valid = false;
                    }
                });

                if ($('#narahubung').val().trim() && !$('#narahubung').val().trim().startsWith('62')) {
                    showFieldError('#narahubung', 'Nomor WhatsApp harus diawali dengan 62.');
                    valid = false;
                }

                if (!$('#tanggal_mulai').val()) {
                    $('#tanggalDisplayText').addClass('is-field-invalid');
                    $('#dateBookingBox').after('<div class="field-error">Tanggal agenda wajib dipilih.</div>');
                    valid = false;
                }

                if (!$('#waktu_mulai').val()) {
                    $('#waktuMulaiDisplay').addClass('is-field-invalid');
                    $('.time-compact-box[data-target="waktu_mulai"]').after('<div class="field-error">Waktu mulai wajib diisi.</div>');
                    valid = false;
                }

                if (!$('#waktu_selesai').val()) {
                    $('#waktuSelesaiDisplay').addClass('is-field-invalid');
                    $('.time-compact-box[data-target="waktu_selesai"]').after('<div class="field-error">Waktu selesai wajib diisi.</div>');
                    valid = false;
                }

                return valid;
            }

            function validateUpload() {
                const file = $('#fileUpload')[0].files[0];
                const allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
                const maxSize = 5 * 1024 * 1024;

                if (!file) {
                    $('.custom-upload-group').addClass('is-field-invalid');
                    $('#fileUploadError').removeClass('d-none').text('Dokumen pendukung wajib diunggah.');
                    return false;
                }

                const ext = file.name.split('.').pop().toLowerCase();

                if (!allowed.includes(ext)) {
                    $('.custom-upload-group').addClass('is-field-invalid');
                    $('#fileUploadError').removeClass('d-none').text('Format file tidak sesuai. Gunakan PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, atau PNG.');
                    return false;
                }

                if (file.size > maxSize) {
                    $('.custom-upload-group').addClass('is-field-invalid');
                    $('#fileUploadError').removeClass('d-none').text('Ukuran file terlalu besar. Maksimal 5MB.');
                    return false;
                }

                return true;
            }

            function validateAgendaRules() {
                let valid = true;

                const jenisAgenda = $('#jenisAgenda').val();
                const meetingType = $('#meeting_type').val();

                if (jenisAgenda === '0' && $('input[name="id_ruang[]"]').length === 0) {
                    showDropdownError('#dropdownRuang', 'Ruangan rapat wajib dipilih.');
                    valid = false;
                }

                if (jenisAgenda === '1') {
                    if (!$('#kepentingan').val()) {
                        $('#kepentinganError').removeClass('d-none').text('Penayangan TV Wall wajib dipilih.');
                        valid = false;
                    }

                    if ((meetingType === 'Luar Kantor' || meetingType === 'Hybrid Luar Kantor') && !$('#nama_tempat').val().trim()) {
                        $('#tempatWrapper').removeClass('d-none');
                        showFieldError('#nama_tempat', 'Tempat pertemuan wajib diisi.');
                        valid = false;
                    }

                    if ((meetingType === 'Luar Kantor' || meetingType === 'Hybrid Luar Kantor') && (!$('#id_provinsi').val() || !$('#id_kabupaten').val())) {
                        if (!$('#id_provinsi').val()) showDropdownError('#dropdownProvinsi', 'Provinsi wajib dipilih.');
                        if (!$('#id_kabupaten').val()) showDropdownError('#dropdownKabupaten', 'Kabupaten/Kota wajib dipilih.');
                        valid = false;
                    }

                    if ((meetingType === 'Online' || meetingType === 'Hybrid Luar Kantor') && $('input[name="id_ruang[]"]').length === 0) {
                        showDropdownError('#dropdownRuang', 'Ruangan Zoom wajib dipilih.');
                        valid = false;
                    }
                }

                return valid;
            }

            function initSavePopup() {
                $(document).on('click', '#savePopup', function (e) {
                    if (e.target.id === 'savePopup') closeSavePopup();
                });
            }

            function closeSavePopup() {
                $('#savePopup').fadeOut(180, function () {
                    $(this).remove();

                    const url = new URL(window.location.href);
                    url.searchParams.delete('saved');
                    window.history.replaceState({}, document.title, url.pathname);
                });
            }
        </script>
    </body>
</html>