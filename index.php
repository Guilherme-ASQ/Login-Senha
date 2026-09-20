<?php
    session_start();
    if($_SERVER["REQUEST_METHOD"] == "POST") {
        //Pega a url do banco de dados
        $databaseUrl = getenv("DATABASE_URL");
        $conexao = pg_connect($databaseUrl);

        //Verifica se a conexão deu certo.
        if(!$conexao) {
            die("Erro ao conectar ao banco de dados.");
        }

        //Formulário de Cadastro para cadastrar novos usuários.
        if(isset($_POST["novoUsuario"]) && isset($_POST["novaSenha"]) && isset($_POST["novoEmail"])) {
        //Pega os dados digitados.
        $novoUsuario = $_POST["novoUsuario"];
        $novaSenha = $_POST["novaSenha"];
        $novoEmail = $_POST["novoEmail"];
        //Vou fazer todos os cadastros dos novos usuários com o tipo "CLIENTE", pq tecnicamente terão poucos clientes do tipo "ADMINISTRADOR". Caso eu precise cadastrar um novo administrador, eu mudo no banco de dados. Fazer isso não é o mais adequado, mas é como eu vou fazer.
        $tipoUsuario = "CLIENTE";

        //Inserir os dados no Banco de Dados
        $resultado = pg_query_params(
            $conexao,
            "INSERT INTO usuarios (nome, senha, email, tipo_usuario) VALUES ($1, $2, $3,$4)",
            array($novoUsuario, $novaSenha, $novoEmail, $tipoUsuario)
        );

        //Verifica se o cadastro funcionou.
        if($resultado) {
            echo "<p>Cadastro realizado com sucesso!</p>";
        }else {
            echo "<p>Erro ao realizar o cadastro.</p>";
            }
        }
        //Formulário de Login
        elseif(isset($_POST["email"]) && isset($_POST["senha"]) ) {
        //Pega o usuário digitado
        //$usuario = $_POST["usuario"];
        
        //Pega o email, pq agora é o email que precisa pra fazer login.
        $email = $_POST["email"];
        //Pega a senha digitada
        $senha = $_POST["senha"];

        //Procura se o usuário existe dentro do banco de dados.
        //Agora procura se o usuário existe com base no email
        $resultado = pg_query_params(
            $conexao,
            "SELECT nome, senha, email, tipo_usuario FROM usuarios WHERE email = $1",
            array($email)
        );

        //Verifica se encontrou o usuario
        if($resultado && pg_num_rows($resultado) > 0 ) {
            //Pega os dados encontrados.
            $dadosUsuario = pg_fetch_assoc($resultado);

            //Compara a senha digitada com a senha armazenada
            if($senha === $dadosUsuario["senha"] ) {
                //Verifica o tipo do usuário
                if($dadosUsuario["tipo_usuario"] === "CLIENTE" ) {
                    //Guarda o nome do usuario na sessão.
                    //A area_cliente.php usará essa informação para descobrir qual usuário está logado.
                    $_SESSION["usuario"] = $dadosUsuario["nome"];
                    //Guarda também o tipo do usuário
                    $_SESSION["tipo_usuario"] = $dadosUsuario["tipo_usuario"];
                    //Redireciona para a área do cliente
                    header("Location: area_cliente.php");

                    //Encerra o código para impedir que o restante da página seja executada.
                    exit;
                }elseif($dadosUsuario["tipo_usuario"] === "ADMINISTRADOR" ) {
                    echo "<p>Login de Administrador realizado com sucesso. Área administrativa ainda não criada.</p>";
                }else{
                    echo "<p>Tipo de Usuário inválido.</p>";
                    }
                }else{
                    echo "<p>Email ou Senha incorretos.</p>";
                    }
            }
        }else{echo "<p>Email ou Senha incorretos.</p>";}
    }
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <!--<meta name="viewport" content="width=device-width, initial-scale=1.0">-->
        <title>LOGIN e SENHA - Conexao BANCO</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body>
       <div>
            <h1>Sistema de LOGIN</h1>
            <!--Formulário para LOGIN-->
            <form method="post" id="form-login">
                <div class="input-group">
                    <label for="email">Email para Login:</label>
                    <input type="email" name="email" id="email" placeholder="Digite seu email" required>
                </div>

                <div class="input-group">
                    <label for="senha">Senha:</label>
                    <input type="password" name="senha" id="senha" placeholder="Digite sua senha" required>
                </div>

                <button type="submit">LOGIN</button>
            </form>

            <h2>CADASTRAR Usuario</h2>
            <!--Formulário-->
            <form method="post" id="form-cadastro">
                <div class="input-group">
                    <label for="novoUsuario">Novo Usuário:</label>
                    <input type="text" name="novoUsuario" id="novoUsuario" placeholder="Digite um usuario" required>
                </div>

                <div class="input-group">
                    <label for="novaSenha">Nova Senha:</label>
                    <input type="password" name="novaSenha" id="novaSenha" placeholder="Digite uma senha" required>
                </div>

                <!--Adicionei coisas novas no cadastro. Agora tem que ter também o email.
                No BAnco de Dados do NEON, tem o tipo do usuario, que pode ser ou ADMINISTRADOR ou CLIENTE.
                Pro cadastro, eu vou sempre passar o tipo do usuario como CLIENTE. Pq tecnicamente serão poucos administradores no sistema.
                Caso eu precise adicionar um novo administrador, eu mudo isso diretamente no banco de dados.
                -->
                <div class="input-group">
                    <label for="novoEmail">Novo Email:</label>
                    <input type="email" name="novoEmail" id="novoEmail" placeholder="Digite um email" required>
                </div>

                <button type="submit">CADASTRAR</button>
            </form>

            <!--<p id="mensagem-cadastro"></p>-->
       </div>

       <!--Código PHP-->
    </body>
</html>
