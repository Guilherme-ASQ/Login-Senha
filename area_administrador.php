<?php
//INICIA A SESSÃO
session_start();

//LOGOUT
//Verifica se o administrador clicou no botão SAIR
if(isset($_SESSION["sair"])){
    //Destrói a sessão
    session_destroy();
    //Volta para a página inicial
    header("Location: index.php");
    exit;
}

//VERIFICA SE O USUÁRIO ESTÁ LOGADO
/*Verifica se existe um usuário dentro da sessão
Se NÃO existir, significa que a pessoa não fez login.
Nesse caso, volta para a página inicial.
*/
if(!isset($_SESSION["usuario"])){
    header("Location: index.php");
    exit;
}

//VERIFICA SE O USUÁRIO É ADMINISTRADOR
/*Aqui verifica o tipo de usuário.
Somento usuários com o tipo: ADMINISTRADOR
podem entrar nesta página.
*/
if(!isset($_SESSION["tipo_usuario"]) || $_SESSION["tipo_usuario"] !== "ADMINISTRADOR" ){
    header("Location: index.php");
    exit;
}

//CONEXÃO COM O BANCO DE DADOS
//Pega a URL do Banco de Dados
$dataBaseUrl = getenv("DATABASE_URL");
//Faz a conexão com o banco
$conexao = pg_connect($dataBaseUrl);

//Verifica se a conexão funcionou
if(!$conexao){
    die("Erro ao conectar ao banco de dados.");
}

//PEGA O USUÁRIO QUE ESTÁ LOGADO
//Pega o nome do usuário que foi armazenado na sessão durante o login.
$usuarioLogado = $_SESSION["email"];

//BUSCA OS DADOS DO ADMINISTRADOR
$resultado = pg_query_params(
    $conexao,
    "SELECT id_usuarios, nome, email, senha FROM usuarios WHERE email = $1 AND tipo_usuario = 'ADMINISTRADOR' ",
    array($usuarioLogado)
);

//VERIFICA SE O USUÁRIO FOI ENCONTRADO
if(!$resultado || pg_num_rows($resultado) == 0 ){
    die("Usuário não encontrado");
}

//Guarda os dados do Usuário
//Pega os dados retornados pelo banco
$dadosUsuario = pg_fetch_assoc($resultado);
//Guarda cada informação em uma variável
$nome = $dadosUsuario["nome"];
$email = $dadosUsuario["email"];
$senha = $dadosUsuario["senha"];

//ALTERAR INFORMAÇÕES
//Verifica se o formulário de edição foi enviado.
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["salvar"]) ){
    //Pega os novos dados enviados pelo formulário
    $novoNome = $_POST["nome"];
    $novoEmail = $_POST["email"];
    $novaSenha = $_POST["senha"];

    //ATUALIZA o nome e email.
    //Se a senha estiver vazia mantem igual
    if($novaSenha == ""){
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios SET nome = $1, email = $2 WHERE email = $3 AND tipo_usuario = 'ADMINISTRADOR' ",
            array($novoNome, $novoEmail, $usuarioLogado)
        );
    }else{
        //Atualiza a senha se o usuario digitou uma nova senha.
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios SET nome = $1, email = $2, senha = $3 WHERE email = $4 AND tipo_usuario = 'ADMINISTRADOR' ",
            array($novoNome, $novoEmail, $novaSenha, $usuarioLogado)
        );
    }

    //Verifica se a alteração funcionou
    if($resultado){
        //Faz a sessão guardar o NOVO nome do usuário.
        //Atualiza a página, desse jeito mostra as novas informações do usuário.
        $_SESSION["usuario"] = $novoNome;
        $_SESSION["email"] = $novoEmail;
        header("Location: area_administrador.php");
        exit;
    }else{
        echo "<p>Erro ao atualizar as informações.</p>";
    }
}
//FIM DO CÓDIGO PHP
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Área do Administrador</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="container">
            <h1>Área do Administrador</h1>

            <div>
                <table class="tabela-usuario">
                    <tr>
                        <th>Informação</th>
                        <th>Dados</th>
                    </tr>

                    <tr>
                        <td>Nome</td>
                        <td>
                            <?php
                            echo htmlspecialchars($nome);
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Email</td>
                        <td>
                            <?php
                            echo htmlspecialchars($email);
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Senha</td>
                        <td>
                            <?php
                            echo str_repeat("*", strlen($senha));
                            ?>
                        </td>
                    </tr>
                </table>

                <!--BOTÃO EDITAR-->
                <br>
                <form method="get">
                    <button type="submit" name="editar" value="1">EDITAR INFORMAÇÕES</button>
                </form>
                <!--BOTÃO SAIR-->
                <br>
                <form method="get">
                    <button type="submit" name="sair" value="1">SAIR</button>
                </form>

            </div>

            <!--FORMULÁRIO DE EDIÇÃO-->
            <?php if(isset($_GET["editar"])): ?>
                <div>
                    <h2>Editar Informações</h2>
                    <form method="post">
                        <div class="input-group">
                            <label for="nome">Nome:</label>
                            <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                        </div>

                        <div class="inpu-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <div class="input-group">
                            <label for="senha">Nova Senha:</label>
                            <input type="password" name="senha" id="senha" placeholder="Digite uma nova senha">

                            <small>Deixe vazio para manter a senha atual.</small>
                        </div>

                        <button type="submit" name="salvar" value="1">SALVAR</button>

                        <a href="area_adminisrador.php">
                            <button type="button">CANCELAR</button>
                        </a>

                    </form>

                </div>
            <!--FIM FORMULÁRIO DE EDIÇÃO-->
            <?php endif; ?>

        </div>
    </body>
</html>
