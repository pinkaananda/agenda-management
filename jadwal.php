<?php
  setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'id', 'indonesian');
  date_default_timezone_set('Asia/Jakarta');
  include "koneksi.php";

  $today = date('Y-m-d');
  $now   = date('H:i:s');

  if(isset($_GET['ajax']) && $_GET['ajax'] == 'agenda'){

  $sql = "
  SELECT *
  FROM tb_agenda_rapat
  WHERE status = 1
  AND (
    (
      id_pertemuan = 1
      AND (
        (tanggal = '$today' AND waktu_selesai >= '$now')
        OR tanggal > '$today'
      )
    )
    OR
    (
      id_pertemuan = 2
      AND tanggal_selesai >= '$today'
    )
  )
  ORDER BY tanggal ASC, waktu_mulai ASC
  ";

    $q = $conn->query($sql);

    $aktif = [];
    $lain  = [];

    while($r = $q->fetch_assoc()){

  if ($r['id_pertemuan'] == 1) {
    $isActive = (
      $r['tanggal'] == $today &&
      $now >= $r['waktu_mulai'] &&
      $now <= $r['waktu_selesai']
    );
  } else if ($r['id_pertemuan'] == 2) {
    $isActive = (
      $r['tanggal'] <= $today &&
      $r['tanggal_selesai'] >= $today &&
      $now >= $r['waktu_mulai'] &&
      $now <= $r['waktu_selesai']
    );
  } else {
    $isActive = false;
  }

    if($isActive) $aktif[] = $r;
    else $lain[] = $r;
  }

    ob_start();
    ?>

    <div class="timeline-group timeline-active">
    <?php foreach($aktif as $a): ?>
      <div class="timeline-item active">
        <div class="timeline-time">
          <div class="tanggal">
            <?php if ($a['id_pertemuan'] == 2 && !empty($a['tanggal_selesai']) && $a['tanggal_selesai'] != $a['tanggal']): ?>
              <?= strftime('%d %B %Y', strtotime($a['tanggal'])); ?> -
              <?= strftime('%d %B %Y', strtotime($a['tanggal_selesai'])); ?>
            <?php else: ?>
              <?= strftime('%d %B %Y', strtotime($a['tanggal'])); ?>
            <?php endif; ?>
          </div>
          <div class="jam">
            <?= substr($a['waktu_mulai'],0,5) ?> - <?= substr($a['waktu_selesai'],0,5) ?>
          </div>
        </div>
        <div class="timeline-icon">
          <span><i class="fa-solid fa-circle-play"></i></span>
        </div>
        <div class="timeline-content">
          <div class="judul"><?= $a['topik_rapat'] ?></div>
          <div class="penyelenggara"><?= $a['penyelenggara'] ?></div>
          <div class="lokasi"><?= $a['nama_ruang'] ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <div class="timeline-group timeline-upcoming">
    <?php foreach($lain as $r): ?>
      <div class="timeline-item">
        <div class="timeline-time">
          <div class="tanggal">
            <?php if ($r['id_pertemuan'] == 2 && !empty($r['tanggal_selesai']) && $r['tanggal_selesai'] != $r['tanggal']): ?>
              <?= strftime('%d %B %Y', strtotime($r['tanggal'])); ?> -
              <?= strftime('%d %B %Y', strtotime($r['tanggal_selesai'])); ?>
            <?php else: ?>
              <?= strftime('%d %B %Y', strtotime($r['tanggal'])); ?>
            <?php endif; ?>
          </div>
          <div class="jam">
            <?= substr($r['waktu_mulai'],0,5) ?> - <?= substr($r['waktu_selesai'],0,5) ?>
          </div>
        </div>
        <div class="timeline-icon">
          <span><i class="fa-solid fa-users"></i></span>
        </div>
        <div class="timeline-content">
          <div class="judul"><?= $r['topik_rapat'] ?></div>
          <div class="penyelenggara"><?= $r['penyelenggara'] ?></div>
          <div class="lokasi"><?= $r['nama_ruang'] ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <?php
    echo ob_get_clean();
    exit;
  }

  $dokumentasi = [];

  if(file_exists('dokumentasi.txt')){
    $lines = file('dokumentasi.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach($lines as $line){
      $parts = explode('|', $line);

      if(count($parts) == 2){
        $dokumentasi[] = [
          'foto' => trim($parts[0]),
          'judul' => trim($parts[1])
        ];
      }
    }
  }

  $infografis = [];

  if(file_exists('infografis.txt')){
    $lines = file('infografis.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach($lines as $line){
      $parts = explode('|', $line);

      if(count($parts) >= 1){
        $infografis[] = [
          'foto' => trim($parts[0])
        ];
      }
    }
  }

  $videoFile = 'youtube.txt';
  $videos = [];

  if(file_exists($videoFile)){
    $lines = file($videoFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach($lines as $line){
      if(preg_match('~(youtu\.be/|v=)([^&?/]+)~', $line, $m)){
        $videos[] = $m[2];
      }
    }
  }


  $sql = "
SELECT *
FROM tb_agenda_rapat
WHERE status = 1
AND (
  (
    id_pertemuan = 1
    AND (
      (tanggal = '$today' AND waktu_selesai >= '$now')
      OR tanggal > '$today'
    )
  )
  OR
  (
    id_pertemuan = 2
    AND tanggal_selesai >= '$today'
  )
)
ORDER BY tanggal ASC, waktu_mulai ASC
";
  $q = $conn->query($sql);

  $aktif = [];
  $lain  = [];

  while($r = $q->fetch_assoc()){

  if ($r['id_pertemuan'] == 1) {
    $isActive = (
      $r['tanggal'] == $today &&
      $now >= $r['waktu_mulai'] &&
      $now <= $r['waktu_selesai']
    );
  } else if ($r['id_pertemuan'] == 2) {
    $isActive = (
      $r['tanggal'] <= $today &&
      $r['tanggal_selesai'] >= $today &&
      $now >= $r['waktu_mulai'] &&
      $now <= $r['waktu_selesai']
    );
  } else {
    $isActive = false;
  }

  if($isActive) $aktif[] = $r;
  else $lain[] = $r;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Agenda Rapat</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
    body{width:100vw;height:98vh;overflow:hidden;background:#22466C; font-size:clamp(14px,1vw,18px);}
    .header{height:12%;padding:clamp(12px,2.8vw,40px);display:flex;justify-content:space-between;align-items:center;background:linear-gradient(180deg,#254766,#2a6099);color:#fff;}
    .header-left{display:flex;align-items:center;gap:clamp(10px,2vw,40px);}
    .logo{max-height:2vw;filter:brightness(0) invert(1);}
    .hero h1{font-size:clamp(24px,2vw,80px);font-weight:700;line-height:1.1;}
    .hero p{font-size:clamp(12px,1vw,60px);}
    .header-time{text-align:right;}
    .header-time .time{font-size:clamp(30px,2vw,200px);font-weight:700;}
    .header-time .date{font-size:clamp(14px,1vw,60px);}
    .container{display: flex;width: calc(100% - (2vw));height: 86%;margin: 1vw;gap: 1vw;}
    .left{display: flex;flex-direction: column;gap: 1vw;width: 63%;}
    .video-wrapper{position:relative;height:100%;border-radius:0.6vw;overflow:hidden;background:linear-gradient(180deg,#254766,#2a6099);}
    #player,#player iframe{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);}
    .docs{height: 36%;padding:0.8vw;background: linear-gradient(180deg, #0c2f45, #1e74ab);border-radius: 0.6vw;display:flex;flex-direction:column;overflow:hidden;}
    .docs-pattern{position:absolute;right:0.6vw;transform: rotate(90deg);width:4vw;}
    .docs-icon{width:1.2vw;top:50%;margin:0 5vw}
    .title{position:relative;color:#fff;padding:0.2vw;display:flex;justify-content:flex-start;align-items:center;margin-top:0.5vw;margin-bottom:0.8vw;gap:0.8vw;border-bottom:0.15vw solid rgba(255,255,255,0.4);}
    .title h3{font-size:clamp(18px,1.2vw,42px);}
    .slider{width:100%;overflow:hidden;height:calc(100% - (2.5vw));}
    .slider-track{display:flex;gap:0.5vw;transition:transform 1.2s ease-in-out;height:100%;}
    .card{flex:0 0 calc((100% - 1vw) / 3);aspect-ratio: auto;border-radius:0.6vw;position:relative;height:100%;overflow:hidden;}
    .card img{width:100%;height:100%;object-fit:cover;}
    .card span{position:absolute;bottom:0;left:0;right:0;padding:0.8vw;font-size:clamp(12px,1vw,32px);background:linear-gradient(transparent,rgba(0,0,0,.65));color:#fff;white-space:normal;overflow:hidden;text-overflow:break-word;text-shadow: 0 2px 6px rgba(0,0,0,0.8);}
    .pattern{position:absolute;transform:rotate(250deg);pointer-events: none;width:20vw;z-index: 0;}
    .pattern.flare{left:-2.5vw;top:-2vw;opacity:0.6;z-index: 1;}
    .right{position:relative;width:37%;height:100%;border-radius:0.6vw;background:linear-gradient(180deg,#0c2f45,#1e74ab);color:#fff;padding:2vw;display:flex;flex-direction:column;overflow:hidden;}
    .agenda-header{display:flex;align-items:center;gap:0.8vw;padding-bottom:0.4vw;border-bottom:0.15vw solid rgba(255,255,255,0.4);margin-bottom:1.5vw;z-index: 3;}
    .agenda-header h3{font-size:clamp(22px,1.6vw,48px);font-weight:700;letter-spacing:1px;}
    .agenda{flex:1;overflow:hidden;display:flex;flex-direction:column;z-index:3;}
    .agenda-static{margin-bottom:2vw;}
    .agenda-scroll-wrapper{position:relative;flex:1;overflow:hidden;}
    .agenda-scroll{position:absolute;width:100%;will-change:transform;}
    .timeline-item{display:grid;grid-template-columns: 25% 8% 67%;align-items:flex-start;position:relative;margin-bottom:1vw;}
    .timeline-time{text-align:right;padding-right:1vw;}
    .timeline-time .tanggal{font-size:clamp(12px,.8vw,30px);opacity:.9;}
    .timeline-time .jam{font-size:clamp(12px,1.1vw,32px);font-weight:700;}
    .timeline-icon{position:relative;display:flex;justify-content:center;}
    .timeline-item{position:relative;}
    .timeline-item::after{content:"";position:absolute;left:29%;top:2.2vw;height:calc(100% + 1.2vw);width:0.18vw;background:rgba(255,255,255,0.4);z-index:0;}
    .timeline-active .timeline-item.active::after{background:#FFD87C;}
    .timeline-active .timeline-item:last-child::after{display:none;}
    .timeline-upcoming .timeline-item:last-child::after{display:none;}
    .timeline-icon{position:relative;z-index:2;display:flex;justify-content:center;}
    .timeline-item.active .timeline-icon span{background:#FFD87C;color:#0c2f45;}
    .timeline-item.active .timeline-time,.timeline-item.active .timeline-content{color:#FFD87C;}
    .timeline-item.active .timeline-icon::after{background:#FFD87C;}
    .timeline-icon span{position:relative;z-index:2;width:2.6vw;height:2.6vw;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:clamp(12px,1vw,28px);background:#ffffff;color:#1e74ab;}
    .timeline-item.active .timeline-time,.timeline-item.active .timeline-content{color:#FFD87C;}
    .timeline-item.active .timeline-icon span{background:#FFD87C;color:#0c2f45;}
    .timeline-content{padding-left:1vw;}
    .timeline-content .judul{font-weight:600;font-size:clamp(12px,1.05vw,30px);margin: 0 0 0.6vw 0}
    .timeline-content .penyelenggara{font-size:clamp(12px,1vw,28px);opacity:0.95;font-weight:400}
    .timeline-content .lokasi{font-size:clamp(12px,1vw,28px);font-style:italic;opacity:.95;font-weight:400}
    .agenda-static{margin-bottom:1vw;}
    .agenda-scroll-wrapper{position:relative;height:100%;overflow:hidden}
    .agenda-scroll{position:absolute;width:100%;will-change:transform}
    .agenda-header h3{font-size:clamp(18px,1.5vw,45px);}
    .agenda-pattern{position:absolute;right:2.5vw;transform: rotate(90deg);width:4.5vw;background:url('dokumen/pattern.svg') no-repeat center/contain;}
    .fa-regular, .fa-solid {font-size: clamp(12px,1.5vw,45px);}
    .video-row{display:flex;gap:1vw;height:64%;}
    .video-wrapper{width:75%}
    .side-photo{width:25%;border-radius:0.6vw;overflow:hidden;background:linear-gradient(180deg,#0c2f45,#1e74ab); position:relative; justify-content: center; }
    .side-track{display:flex;height:100%;transition:transform 1s ease-in-out;}
    .side-item{flex:0 0 100%;height:100%; padding:0.8vw}
    .side-photo img{width:100%;height:100%;object-fit:cover;border-radius:0.6vw}
  </style>
</head>
<body>
    <header class="header">
      <div class="header-left">
        <img src="dokumen/logoUmkm.png" class="logo" alt="logo">
        <div class="hero">
          <h1>Selamat Datang</h1>
          <p>di Deputi Bidang Usaha Menengah Kementerian UMKM RI</p>
        </div>
      </div>
      <div class="header-time">
        <div class="time" id="jam"></div>
        <div class="date" id="tanggal"></div>
      </div>
    </header>
    <div class="container">
      <div class="left">
        <div class="video-row">
          <div class="video-wrapper">
            <div class="video-main">
              <div id="player"></div>
            </div>
          </div>
          <div class="side-photo">
            <div class="side-track" id="sideTrack">
              <?php foreach($infografis as $info): ?>
                <div class="side-item">
                  <img src="<?= $info['foto'] ?>" alt="">
                </div>
              <?php endforeach; ?>
            </div>
          </div>
      </div>
        <div class="docs">
         <div class="title">
            <i class="fa-solid fa-images"></i>
            <h3>Dokumentasi Kegiatan</h3>
            <img src="dokumen/pola.png" class="docs-pattern">
          </div>
          <div class="slider">
            <div class="slider-track" id="sliderTrack">
              <?php foreach($dokumentasi as $doc): ?>
                <div class="card">
                  <img src="<?= $doc['foto'] ?>" alt="">
                  <span><?= $doc['judul'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="right">
        <img src="dokumen/pernik-umkm.png" alt="pattern" class="pattern flare">
        <div class="agenda-header">
          <i class="fa-regular fa-calendar-days"></i>
          <img src="dokumen/pola.png" class="agenda-pattern">
          <h3>Agenda Rapat</h3>
        </div>
        <div class="agenda">
        <div class="agenda-scroll-wrapper">
          <div class="agenda-scroll" id="agendaScroll">
            <div class="timeline-group timeline-active">
              <?php foreach($aktif as $a): ?>
                <div class="timeline-item active">
                  <div class="timeline-time">
                    <div class="tanggal">
                      <?php if ($r['id_pertemuan'] == 2 && !empty($r['tanggal_selesai']) && $r['tanggal_selesai'] != $r['tanggal']): ?>
                        <?= strftime('%d %B %Y', strtotime($r['tanggal'])); ?> -
                        <?= strftime('%d %B %Y', strtotime($r['tanggal_selesai'])); ?>
                      <?php else: ?>
                        <?= strftime('%d %B %Y', strtotime($r['tanggal'])); ?>
                      <?php endif; ?>
                    </div>
                    <div class="jam">
                      <?= substr($a['waktu_mulai'],0,5) ?> - <?= substr($a['waktu_selesai'],0,5) ?>
                    </div>
                  </div>
                  <div class="timeline-icon">
                    <span><i class="fa-solid fa-circle-play"></i></span>
                  </div>
                  <div class="timeline-content">
                    <div class="judul"><?= $a['topik_rapat'] ?></div>
                    <div class="penyelenggara"><?= $a['penyelenggara'] ?></div>
                    <div class="lokasi"><?= $a['nama_ruang'] ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="timeline-group timeline-upcoming">
              <?php foreach($lain as $r): ?>
                <div class="timeline-item">
                  <div class="timeline-time">
                    <div class="tanggal">
                      <?php if ($r['id_pertemuan'] == 2 && !empty($r['tanggal_selesai']) && $r['tanggal_selesai'] != $r['tanggal']): ?>
                        <?= strftime('%d %B %Y', strtotime($r['tanggal'])); ?> -
                        <?= strftime('%d %B %Y', strtotime($r['tanggal_selesai'])); ?>
                      <?php else: ?>
                        <?= strftime('%d %B %Y', strtotime($r['tanggal'])); ?>
                      <?php endif; ?>
                    </div>
                    <div class="jam">
                      <?= substr($r['waktu_mulai'],0,5) ?> - <?= substr($r['waktu_selesai'],0,5) ?>
                    </div>
                  </div>
                  <div class="timeline-icon">
                    <span><i class="fa-solid fa-users"></i></span>
                  </div>
                  <div class="timeline-content">
                    <div class="judul"><?= $r['topik_rapat'] ?></div>
                    <div class="penyelenggara"><?= $r['penyelenggara'] ?></div>
                    <div class="lokasi"><?= $r['nama_ruang'] ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
        </div>
      </div>
    </div>
          </div>
      </div>
    </div>
  <script src="https://www.youtube.com/iframe_api"></script>
  <script>
    function loadAgenda(){
      fetch(window.location.pathname + '?ajax=agenda')
        .then(response => response.text())
        .then(data => {
          document.getElementById('agendaScroll').innerHTML = data;
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
      loadAgenda();
    });
    setInterval(loadAgenda, 3000);

    function updateTime(){
      const n = new Date();
      const jamStr =String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0');

      jam.innerHTML = jamStr;
      tanggal.innerHTML = n.toLocaleDateString('id-ID',{day:'2-digit', month:'long', year:'numeric'});
    }
    setInterval(updateTime,1000);
    updateTime();

    const track = document.getElementById('sliderTrack');
    const slidesPerView = 3;

    track.innerHTML += track.innerHTML;

    let indexSlide = 0;

    function slide(){
      const slider = document.querySelector('.slider');
      const slideWidth = slider.offsetWidth;
      const step = slideWidth;

      indexSlide++;

      track.style.transform = `translateX(-${step * indexSlide}px)`;

      if(indexSlide >= track.children.length / slidesPerView / 2){
        setTimeout(()=>{
          track.style.transition = 'none';
          indexSlide = 0;
          track.style.transform = 'translateX(0)';
          track.offsetHeight;
          track.style.transition = 'transform 1.2s ease-in-out';
        },3000);
      }
    }
    setInterval(slide, 12000);
    
    const sideTrack = document.getElementById('sideTrack');

    if(sideTrack){

      const totalSide = sideTrack.children.length;

      if(totalSide > 1){

        sideTrack.innerHTML += sideTrack.innerHTML;
        let indexSide = 0;

        function slideSide(){

          const width = document.querySelector('.side-photo').offsetWidth;
          indexSide++;
          sideTrack.style.transform = `translateX(-${width * indexSide}px)`;

          if(indexSide >= totalSide){
            setTimeout(()=>{
              sideTrack.style.transition = 'none';
              indexSide = 0;
              sideTrack.style.transform = 'translateX(0)';
              sideTrack.offsetHeight;
              sideTrack.style.transition = 'transform 1s ease-in-out';
            },12000);
          }
        }
        setInterval(slideSide, 3000);
      }
    }

    const agendaScroll = document.getElementById('agendaScroll');

    if(agendaScroll){

      const totalItems = agendaScroll.querySelectorAll('.timeline-item').length;

      if(totalItems > 4){

        agendaScroll.innerHTML += agendaScroll.innerHTML;

        let y = 0;
        const speed = 0.3;

        setInterval(()=>{
          y += speed;
          if(y >= agendaScroll.scrollHeight / 2) y = 0;
          agendaScroll.style.transform = `translateY(-${y}px)`;
        },20);
      }
    }

    let player;
    const VIDEO_RATIO = 16 / 9;
    const videos = <?= json_encode($videos) ?>;

    let index = 0;
    function onYouTubeIframeAPIReady(){
      player = new YT.Player('player',{
        videoId: videos[index],
        playerVars:{
          autoplay:1,
          mute:1,
          enablejsapi:1,
          playsinline:1,
        },
        events:{
          onReady: onPlayerReady,
          onStateChange: onPlayerStateChange
        }
      });
    }

    function onPlayerReady(e){
      e.target.playVideo();
      resizePlayer();
      window.addEventListener('resize', resizePlayer);
    }

    function onPlayerStateChange(e){
      if(e.data === YT.PlayerState.ENDED){
        index = (index + 1) % videos.length;
        player.loadVideoById(videos[index]);
      }
    }

    function resizePlayer(){
      const wrapper = document.querySelector('.video-wrapper');
      const w = wrapper.offsetWidth;
      const h = wrapper.offsetHeight;

      let playerWidth, playerHeight;

      if(w / h > VIDEO_RATIO){
        playerWidth  = w;
        playerHeight = w / VIDEO_RATIO;
      }else{
        playerHeight = h;
        playerWidth  = h * VIDEO_RATIO;
      }
      player.setSize(playerWidth, playerHeight);
    }
  </script>
</body>
</html>