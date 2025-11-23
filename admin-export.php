<?php
include 'config.php';
if (!isLoggedIn() || !isAdmin()) {
  header('Location: login.php');
  exit;
}
$pdo = getPDO();
$type = (($_GET['type'] ?? 'ads') === 'users') ? 'users' : 'ads';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="quickannonce_'.$type.'_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
if ($type === 'users') {
  fputcsv($out, ['id','nom','email','nb_annonces','role']);
  $stmt = $pdo->query('SELECT id, nom, email, COALESCE(nb_annonces,0) AS nb_annonces, role FROM utilisateurs ORDER BY id');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
  }
} else {
  fputcsv($out, ['id','titre','description','date_publication','etat','moderation_status','image_path','id_utilisateur']);
  $stmt = $pdo->query("SELECT id, titre, description, date_publication, etat, moderation_status, image_path, id_utilisateur FROM annonces ORDER BY id");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
  }
}
fclose($out);
exit;
?>
