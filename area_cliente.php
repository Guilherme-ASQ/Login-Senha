<?php
//Inicia a sessão
session_start();

//Verifica se existe um usuário logado
if(!isset($_SESSION["usuario"]) ){
    //Se não existe usuários na sessão, volta para a página de login.
    header("Location: index.php");
    exit;
}

//Verifica se o Usuário é Cliente
if(!isset($_SESSION["tipo_usuario"]) || $_SESSION["tipo_usuario"] !== "CLIENTE" ) {
    //Se não for CLIENTE, volta para o login
    header("Location: index.php");
    exit;
}

//Conexão com o Banco
$databaseUrl = getenv("DATABASE_URL");
$conexao = pg_connect($databaseUrl);

//Verifica se a conexão funcionou
if(!$conexao) {
    die("Erro ao conectar ao banco de dados.");
}

//Pega o usuário que está logado.
$usuarioLogado = $_SESSION["usuario"];

//Salvar alterações
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["salvar"]) ) {
    //Pega os novos dados enviados pelo formulários
    $novoNome = $_POST["nome"];
    $novoEmail = $_POST["email"];
    $novaSenha = $_POST["senha"];

    //Se a senha estiver vazia, mantém a senha antiga.
    if($novaSenha == "") {
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios SET nome = $1, email = $2 WHERE nome = $3 AND tipo_usuario = 'CLIENTE' ",
            array($novoNome, $novoEmail, $usuarioLogado)
        );
    }else{
        //Se o usuário digitou uma senha, atualiza também a senha.
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios SET nome = $1, email = $2, senha = $3 WHERE nome = $4 AND tipo_usuario = 'CLIENTE'",
            array($novoNome, $novoEmail, $novaSenha, $usuarioLogado)
        );
    }

    //Verifica se a Alteração funcionou
    if($resultado) {
        //Atualiza o nome armazenado na sessão.
        $_SESSION["usuario"] = $novoNome;

        header("Location: area_cliente.php");
        exit;

        //Mostra a mensagem de sucesso
        //echo "<p>Informações atuzalizadas com sucesso!</p>";

        //Atualiza  variável usada posteriormente
        //$usuarioLogado = $novoNome;
    }else{
        echo "<p>Erro ao atualizar as informações.</p>";
    }
}

//Busca os dados do cliente
$resultado = pg_query_params(
    $conexao,
    "SELECT nome, email, senha FROM usuarios WHERE nome = $1 AND tipo_usuario = 'CLIENTE'",
    array($usuarioLogado)
);

//Verifica se encontrou o usuário.
if(!$resultado || pg_num_rows($resultado) == 0 ) {
    die("Usuário não encontrado.");
}

//Pega os dados do banco
$dadosUsuario = pg_fetch_assoc($resultado);

//Guarda cada informação em uma variável.
$nome = $dadosUsuario["nome"];
$email = $dadosUsuario["email"];
$senha = $dadosUsuario["senha"];

//FIM DO CÓDIGO PHP
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Área do Cliente</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div>
            <h1>Área do Cliente</h1>

            <div>
                <table class="tabela-usuario">
                    <tr>
                        <th>Informação</th>
                        <th>Dados</th>
                    </tr>

                    <tr>
                        <td>Nome</td>
                        <td>
                            <?php echo htmlspecialchars($nome); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Email</td>
                        <td>
                            <?php echo htmlspecialchars($email); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Senha</td>
                        <td>
                            <?php echo str_repeat("*", strlen($senha)); ?>
                        </td>
                    </tr>
                </table>

                <!--Botão Editar-->
                <br>
                <form method="get">
                    <button type="submit" name="editar" value="1">EDITAR INFORMAÇÕES</button>
                </form>
            </div>

            <!--Formulário de Edição-->
            <?php if(isset($_GET["editar"])): ?>
            <div>
                <h2>Editar Informações</h2>

                <form method="post">
                    <!--NOME-->
                    <div class="input-group">
                        <label for="nome">Nome:</label>
                        <input type="text" name="nome" id="nome" value=" <?php echo htmlspecialchars($nome); ?>" required>
                    </div>
                    
                    <!--EMAIL-->
                    <div class="input-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?> " required>
                    </div>

                    <!--SENHA-->
                    <div class="input-group">
                        <label for="senha">Nova Senha:</label>
                        <input type="password" name="senha" id="senha" placeholder="Digite uma nova senha">
                        <small>Deixe vazio para manter a Senha atual.</small>
                    </div>

                    <!--Botao Salvar-->
                    <button type="submit" name="salvar" value="1">SALVAR</button>
                    <!--Botao Cancelar-->
                    <a href="area_cliente.php">
                        <button type="button">CANCELAR</button>
                    </a>
                </form>
            </div>

            <?php endif; ?>

        </div>
    </body>
</html>
