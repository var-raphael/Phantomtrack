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
    <link rel="stylesheet" href="assets/font-awesome/icons/css/all.min.css">
    <link rel="stylesheet" href="assets/font-awesome/icons/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/font-awesome/icons/css/brands.min.css">
    
    <script>
  (function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
  })();
</script>
    
       <script src="assets/js/htmx.min.js"></script>
          
</head>