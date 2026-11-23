<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    // 1. Liga ao MySQL e cria o banco se não existir
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `biblioteca_integrador` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `biblioteca_integrador`");

    // 2. Cria a tabela autores se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `autores` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(120) NOT NULL,
            `nacionalidade` VARCHAR(80) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. Cria a tabela livros ligada a autores se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `livros` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `titulo` VARCHAR(180) NOT NULL,
            `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `autor_id` INT NOT NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_livros_autores`
                FOREIGN KEY (`autor_id`) REFERENCES `autores` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4. Insere autores e livros iniciais apenas se estiverem vazios
    $totalAutores = (int)$pdo->query("SELECT COUNT(*) FROM `autores`")->fetchColumn();
    if ($totalAutores === 0) {
        $pdo->exec("
            INSERT INTO `autores` (`id`, `nome`, `nacionalidade`) VALUES
            (1, 'Ramez Elmasri', 'Americana'),
            (2, 'Autor Exemplo', 'Brasileira')
        ");
    }

    $totalLivros = (int)$pdo->query("SELECT COUNT(*) FROM `livros`")->fetchColumn();
    if ($totalLivros === 0) {
        $pdo->exec("
            INSERT INTO `livros` (`titulo`, `preco`, `autor_id`) VALUES
            ('Banco de Dados', 180.00, 1),
            ('PHP para Iniciantes', 90.00, 2)
        ");
    }
} catch (PDOException $e) {
    die('Erro na conexão ou criação das tabelas: ' . $e->getMessage());
}

$mensagem = '';
$erro = '';
$livroEditar = null;

// CADASTRAR LIVRO (Prepared Statement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    $titulo   = trim($_POST['titulo'] ?? '');
    $preco    = (float)($_POST['preco'] ?? 0);
    $autor_id = (int)($_POST['autor_id'] ?? 0);

    if ($titulo === '' || $preco < 0 || $autor_id <= 0) {
        $erro = 'Preencha título, preço válido e selecione um autor.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO livros (titulo, preco, autor_id) VALUES (:titulo, :preco, :autor_id)');
        $stmt->execute([
            ':titulo'   => $titulo,
            ':preco'    => $preco,
            ':autor_id' => $autor_id,
        ]);
        $mensagem = 'Livro cadastrado com sucesso!';
    }
}

// CARREGAR LIVRO PARA EDITAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar_form') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, titulo, preco, autor_id FROM livros WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $livroEditar = $stmt->fetch();
    }
}

// SALVAR EDIÇÃO (UPDATE com Prepared Statement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar') {
    $id       = (int)($_POST['id'] ?? 0);
    $titulo   = trim($_POST['titulo'] ?? '');
    $preco    = (float)($_POST['preco'] ?? 0);
    $autor_id = (int)($_POST['autor_id'] ?? 0);

    if ($id <= 0 || $titulo === '' || $preco < 0 || $autor_id <= 0) {
        $erro = 'Dados inválidos para atualizar o livro.';
    } else {
        $stmt = $pdo->prepare('UPDATE livros SET titulo = :titulo, preco = :preco, autor_id = :autor_id WHERE id = :id');
        $stmt->execute([
            ':titulo'   => $titulo,
            ':preco'    => $preco,
            ':autor_id' => $autor_id,
            ':id'       => $id,
        ]);
        $mensagem = 'Livro atualizado com sucesso!';
    }
}

// APAGAR LIVRO (POST + Prepared Statement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'apagar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM livros WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $mensagem = 'Livro apagado com sucesso!';
    }
}

// BUSCAR AUTORES PARA O SELECT
$autores = $pdo->query('SELECT id, nome FROM autores ORDER BY nome ASC')->fetchAll();

// CONSULTA COM JOIN (livros + autores)
$livros = $pdo->query('
    SELECT l.id, l.titulo, l.preco, l.autor_id, a.nome AS autor_nome
    FROM livros l
    INNER JOIN autores a ON l.autor_id = a.id
    ORDER BY l.id DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Mini-Projeto Biblioteca Integrador</title>
</head>
<body>
    <h1>Biblioteca Integrador (Autores + Livros)</h1>

    <?php if ($mensagem): ?>
        <p style="color: green;"><strong><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <?php if ($erro): ?>
        <p style="color: red;"><strong><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <?php if ($livroEditar): ?>
        <h2>Editar Livro #<?= (int)$livroEditar['id'] ?></h2>
        <form method="POST">
            <input type="hidden" name="acao" value="atualizar">
            <input type="hidden" name="id" value="<?= (int)$livroEditar['id'] ?>">
            <input type="text" name="titulo" value="<?= htmlspecialchars($livroEditar['titulo'], ENT_QUOTES, 'UTF-8') ?>" required>
            <input type="number" step="0.01" name="preco" value="<?= htmlspecialchars((string)$livroEditar['preco'], ENT_QUOTES, 'UTF-8') ?>" required>
            <select name="autor_id" required>
                <?php foreach ($autores as $autor): ?>
                    <option value="<?= (int)$autor['id'] ?>" <?= (int)$autor['id'] === (int)$livroEditar['autor_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($autor['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Atualizar</button>
            <a href="index.php">Cancelar</a>
        </form>
    <?php else: ?>
        <h2>Cadastrar Livro</h2>
        <form method="POST">
            <input type="hidden" name="acao" value="cadastrar">
            <input type="text" name="titulo" placeholder="Título do livro" required>
            <input type="number" step="0.01" name="preco" placeholder="Preço" required>
            <select name="autor_id" required>
                <option value="">Selecione o autor</option>
                <?php foreach ($autores as $autor): ?>
                    <option value="<?= (int)$autor['id'] ?>">
                        <?= htmlspecialchars($autor['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Salvar</button>
        </form>
    <?php endif; ?>

    <br>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Autor (JOIN)</th>
            <th>Preço</th>
            <th>Ações</th>
        </tr>
        <?php foreach ($livros as $livro): ?>
            <tr>
                <td><?= (int)$livro['id'] ?></td>
                <td><?= htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($livro['autor_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>R$ <?= number_format((float)$livro['preco'], 2, ',', '.') ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="editar_form">
                        <input type="hidden" name="id" value="<?= (int)$livro['id'] ?>">
                        <button type="submit">Editar</button>
                    </form>
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