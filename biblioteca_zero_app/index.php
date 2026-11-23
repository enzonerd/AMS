<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `biblioteca_zero` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `biblioteca_zero`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `livros` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `titulo` VARCHAR(180) NOT NULL,
            `autor` VARCHAR(120) NOT NULL,
            `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $total = (int)$pdo->query("SELECT COUNT(*) FROM `livros`")->fetchColumn();
    if ($total === 0) {
        $pdo->exec("
            INSERT INTO `livros` (`titulo`, `autor`, `preco`) VALUES
            ('Banco de Dados', 'Ramez Elmasri', 180.00),
            ('PHP para Iniciantes', 'Autor Exemplo', 90.00)
        ");
    }
} catch (PDOException $e) {
    die('Erro na conexão ou criação do banco: ' . $e->getMessage());
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    $titulo = trim($_POST['titulo'] ?? '');
    $autor  = trim($_POST['autor'] ?? '');
    $preco  = (float)($_POST['preco'] ?? 0);

    if ($titulo === '' || $autor === '' || $preco < 0) {
        $erro = 'Preencha título, autor e preço válido.';
    } else {
        $sql = $pdo->prepare('INSERT INTO livros (titulo, autor, preco) VALUES (:titulo, :autor, :preco)');
        $sql->execute([
            ':titulo' => $titulo,
            ':autor'  => $autor,
            ':preco'  => $preco,
        ]);
        $mensagem = 'Livro cadastrado com sucesso!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'apagar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $sql = $pdo->prepare('DELETE FROM livros WHERE id = :id');
        $sql->execute([':id' => $id]);
        $mensagem = 'Livro apagado com sucesso!';
    }
}

$livros = $pdo->query('SELECT * FROM livros ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Biblioteca Zero</title>
</head>
<body>
    <h1>Biblioteca - Livros</h1>

    <?php if ($mensagem): ?>
        <p style="color: green;"><strong><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <?php if ($erro): ?>
        <p style="color: red;"><strong><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="acao" value="cadastrar">
        <input type="text" name="titulo" placeholder="Título do livro" required>
        <input type="text" name="autor" placeholder="Autor" required>
        <input type="number" step="0.01" name="preco" placeholder="Preço" required>
        <button type="submit">Salvar</button>
    </form>

    <br>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Autor</th>
            <th>Preço</th>
            <th>Ação</th>
        </tr>
        <?php foreach ($livros as $livro): ?>
            <tr>
                <td><?= (int)$livro['id'] ?></td>
                <td><?= htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($livro['autor'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>R$ <?= number_format((float)$livro['preco'], 2, ',', '.') ?></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Apagar este livro?');">
                        <input type="hidden" name="acao" value="apagar">
                        <input type="hidden" name="id" value="<?= (int)$livro['id'] ?>">
                        <button type="submit">Apagar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>