<?php
require_once 'config.php';

if (!estConnecte() || !estAdmin()) {
    header("Location: login.php");
    exit();
}

// Traitement des actions admin
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['bloquer'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE utilisateur SET est_bloque = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Utilisateur bloqué avec succès";
    } elseif (isset($_POST['debloquer'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE utilisateur SET est_bloque = 0 WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Utilisateur débloqué avec succès";
    } elseif (isset($_POST['supprimer'])) {
        $user_id = $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = "Utilisateur supprimé avec succès";
        } else {
            $error = "Vous ne pouvez pas supprimer votre propre compte";
        }
    }
}

// Récupérer tous les utilisateurs
$stmt = $pdo->query("SELECT id, username, est_bloque, est_admin, created_at FROM utilisateur ORDER BY id DESC");
$utilisateurs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Chat Furaha</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Administration du Chat</h1>
            <div class="header-actions">
                <a href="chat.php" class="btn">Retour au chat</a>
                <a href="logout.php" class="btn btn-logout">Déconnexion</a>
            </div>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="users-table">
            <h2>Gestion des utilisateurs</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Statut</th>
                        <th>Rôle</th>
                        <th>Date d'inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($utilisateurs as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <?php if($user['est_bloque']): ?>
                                    <span class="badge badge-blocked">Bloqué</span>
                                <?php else: ?>
                                    <span class="badge badge-active">Actif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($user['est_admin']): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-user">Utilisateur</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td class="actions">
                                <?php if(!$user['est_bloque'] && !$user['est_admin']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="bloquer" class="btn btn-block">Bloquer</button>
                                    </form>
                                <?php elseif($user['est_bloque'] && !$user['est_admin']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="debloquer" class="btn btn-unblock">Débloquer</button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if(!$user['est_admin'] && $user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="supprimer" class="btn btn-delete">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>