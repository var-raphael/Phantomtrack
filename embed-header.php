<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Phantom Track - Embeded Dashboard</title>
    <link rel="stylesheet" href="assets/css/theme/<?php echo $theme; ?>.css">
    <link rel="stylesheet" href="assets/css/embed-style.css">
    <link rel="stylesheet" href="assets/css/embed-dashboard.css">
    <link rel="stylesheet" href="assets/css/modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"/>
    <script>
  (function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
  })();
</script>
    
       <script src="assets/js/htmx.min.js"></script>
          <script src="https://phantomtrack-cdn.vercel.app/phantom.v1.0.0.js?trackid=track_428e608b90b694c4bee3"></script>
</head>