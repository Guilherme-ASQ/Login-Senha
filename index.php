<DOCTYPE HTML>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-widht, initial-scale=1.0">
        <title>LOGIN e SENHA - Conexao BANCO</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body>
       <div>
            <h1>CADASTRAR Usuario</h1>
            <!--Formulário-->
            <form id="form-cadastro">
                <div class="input-group">
                    <label for="novoUsuario">Novo Usuário:</label>
                    <input type="text" id="novoUsuario" placeholder="Digite um usuario" required>
                </div>

                <div class="input-group">
                    <label for="novaSenha">Nova Senha:</label>
                    <input type="password" id="novaSenha" placeholder="Digite uma senha" required>
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
            }
       ?>
    </body>
</html>