<DOCTYPE HTML>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <!--<meta name="viewport" content="width=device-widht, initial-scale=1.0">-->
        <title>LOGIN e SENHA - Conexao BANCO</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body>
       <div>
            <h1>CADASTRAR Usuario</h1>
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

                <button type="submit">CADASTRAR</button>
            </form>

            <p id="mensagem-cadastro"></p>
       </div>

       <!--Código PHP-->
       <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                # code...
                //Recebe as informações do Banco de Dados e salva no Banco.
                $novoUsuario = $_POST["novoUsuario"];
                $novaSenha = $_POST["novaSenha"];

                //Obtem a conexao com o banco de dados no render
                $databaseUrl = getenv("DATABASE_URL");

                //Conexão com o site ao PostgreeSQL
                $conexao = pg_connect($databaseUrl);

                pg_query_params(
                    $conexao,
                    "INSERT INTO usuarios (nome, senha) VALUES ($1, $2)",
                    array($novoUsuario, $novaSenha)
                );

                //Mostra a confirmação
                echo "Cadastro realizado com sucesso!";
            }
       ?>
    </body>
</html>
