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
                    <label for="usuario">Usuário:</label>
                    <input type="text" name="usuario" id="usuario" placeholder="Digite seu usuario" required>
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
       <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                # code...
                //Recebe as informações do Banco de Dados e salva no Banco.
                //$novoUsuario = $_POST["novoUsuario"];
                //$novaSenha = $_POST["novaSenha"];

                //Obtem a conexao com o banco de dados no render
                $databaseUrl = getenv("DATABASE_URL");

                //Conexão com o site ao PostgreeSQL
                $conexao = pg_connect($databaseUrl);

                //Verifica se a conexão funciona
                if(!$conexao){
                    die("Erro ao conectar ao banco de dados.");
                }

                //Verifica qual o formulário foi enviado 
                    //Formulario de Cadastro
                    if(isset($_POST["novoUsuario"]) && isset($_POST["novaSenha"]) && isset($_POST["novoEmail"]) ){
                        //Pega os dados digitados no formulário
                        $novoUsuario = $_POST["novoUsuario"];
                        $novaSenha = $_POST["novaSenha"];
                        $novoEmail = $_POST["novoEmail"];
                        //COmo eu vou cadastrar os usuarios como CLIENTE, pq terão poucos administradores,
                        //por padrão todos os clientes serão cadastrados como CLIENTE. Caso precise, eu
                        //mudo no banco de dados mesmo, com os comandos SQL.
                        $tipoUsuario = "CLIENTE";

                        //Insere o novo usuario no banco
                        $resultado = pg_query_params(
                            $conexao,
                            "INSERT INTO usuarios (nome, senha, email, tipo_usuario) VALUES ($1, $2, $3, $4)",
                            array($novoUsuario, $novaSenha, $novoEmail, $tipoUsuario)
                        );

                        //Verifica se o cadastro funcionou
                        if($resultado){
                            echo "<p>Cadastro realizado com sucesso!</p>";
                        }else{
                            echo "<p>Erro ao realizar o cadastro.</p>";
                        }
                    }

                    //Formulário de Login
                    elseif(isset($_POST["usuario"]) && isset($_POST["senha"]) ){
                        //Pega o usuario e senha digitado
                        $usuario = $_POST["usuario"];
                        $senha = $_POST["senha"];

                        //Procura no banco de dados um usuario com o mesmo nome
                        $resultado = pg_query_params(
                            $conexao,
                            "SELECT nome, senha FROM usuarios WHERE nome = $1",
                            array($usuario)
                        );

                        //Verifica se encontrou algum usuario
                        if($resultado && pg_num_rows($resultado) > 0){
                            //Pega os dados encontrados no banco.
                            $dadosUsuario = pg_fetch_assoc($resultado);

                            //Compara a senha digitada com a senha armazenada.
                            if($senha === $dadosUsuario["senha"]){
                                echo "<p>Login realizado com sucesso!</p>";
                            }else{
                                echo "<p>Usuario ou Senha incorretos.</p>";
                            }
                        }else{
                            echo "<p>Usuario ou Senha incorretos.</p>";
                        }
                    }
                

                //Código do Professor e que funcionou pro cadastro.
                /*pg_query_params(
                    $conexao,
                    "INSERT INTO usuarios (nome, senha) VALUES ($1, $2)",
                    array($novoUsuario, $novaSenha)
                );

                //Mostra a confirmação
                echo "Cadastro realizado com sucesso!";*/
            }
       ?>
    </body>
</html>
