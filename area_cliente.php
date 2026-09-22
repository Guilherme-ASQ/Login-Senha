<?php
//Inicia a sessão
session_start();

//Verifica se o usuário clicou no botão SAIR
if(isset($_GET["sair"])) {
    //Detrói a sessão do usuário
    session_destroy();
    //Volta para a página de login
    header("Location: index.php");
    exit;
}

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

//Busca os dados do usuario
/*Busca os dados do usuário no banco. Pegamos o nome, email,
senha, e agora pegamos o id_usuarios.
Esse ID será utilizado para relazionar o usuário aos serviços
*/
$resultado = pg_query_params(
    $conexao,
    "SELECT id_usuarios, nome, email, senha FROM usuarios WHERE nome = $1 AND tipo_usuario = 'CLIENTE' ",
    array($usuarioLogado)
);
/*MUDAR DEPOIS O COMANDO SELECT. Mudar a parte do WHERE pra email no lugar de nome
"SELECT id_usuarios, nome, email, senha FROM usuarios WHERE email = $1 AND tipo_usuario = 'CLIENTE' ",
*/

//Verifica se encontrou o usuário.
if(!$resultado || pg_num_rows($resultado) == 0 ) {
    die("Usuário não encontrado.");
}

//GUARDA OS DADOS DO USUÁRIOS
//Pega os dados retornados pelo banco
$dadosUsuario = pg_fetch_assoc($resultado);

//Guarda o ID do usuário
$idUsuario = $dadosUsuario["id_usuarios"];

//Guarda o nome
$nome = $dadosUsuario["nome"];
//Guarda o email
$email = $dadosUsuario["email"];
//Guarda a senha
$senha = $dadosUsuario["senha"];

//Verifica se o formulário de edição foi enviado
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

/*Solicitar NOVO SERVIÇO*/
//Verifica se o formulário de novo serviço foi enviado
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["solicitar_servico"])) {
    //Pega a descrição digitada pelo usuário
    $descricao = $_POST["descricao"];
    //O novo serviço começa com o status SOLICITADO
    $status = "SOLICITADO";

    /*Inseri o serviço no banco de dados
    id_usuario recebe o ID do usuário logado
    descricao recebe o texto digitado.
    status recebe SOLICITADO.
    */
    $resultado = pg_query_params(
        $conexao,
        "INSERT INTO servicos (descricao, status, id_usuario) VALUES ($1, $2, $3)",
        array($descricao, $status, $idUsuario)
    );

    //Verifica se o cadastro funcionou
    if($resultado) {
        /*Volta para a página
        Isso faz com que oformulário desapareça
        e a tabela de serviços seja mostrada novamente
        */
        header("Location: area_cliente.php");
        exit;
    }else{
        echo "<p>Erro ao solicitar o serviço.</p>";
    }
}

//Cancelar Serviço
//Verifica se o botão CANCELAR foi pressionado
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cancelar_servico"]) ) {
    //Pega o ID do serviço que será cancelado
    $idServico = $_POST["id_servico"];
    /*Altera o status do serviço para CANCELADO
    Também verificamos id_cliente.
    Isso impede que um cliente tente cancelar um serviço
    pertencente a outro cliente.
    */
    $resultado = pg_query_params(
        $conexao,
        "UPDATE servicos SET status = 'CANCELADO' WHERE id_servicos = $1 AND id_usuario = $2",
        array($idServico, $idUsuario)
    );

    //Verifica se a alteração funcionou
    if($resultado) {
        //Atualiza a página
        header("Location: area_cliente.php");
        exit;
    }else{
        echo "<p>Erro ao cancelar o serviço.</p>";
    }
}

//Busca os serviços do Usuário
/*Busca somente os serviços pertencentes ao usuário que está
logado. A ligação é:
usuarios.id_usuarios
servicos.id_usuario

Portanto, cada cliente cerá somente os próprios serviços.
*/
$resultadoServicos = pg_query_params(
    $conexao,
    "SELECT id_servicos, descricao, status FROM servicos WHERE id_usuario = $1 ORDER BY id_servicos DESC",
    array($idUsuario)
);

//Verifica se a busca funcionou
if(!$resultadoServicos){
    die("Erro ao buscar os serviços.");
}

//FIM DO CÓDIGO PHP

//Busca os dados do cliente
/*$resultado = pg_query_params(
    $conexao,
    "SELECT nome, email, senha FROM usuarios WHERE nome = $1 AND tipo_usuario = 'CLIENTE'",
    array($usuarioLogado)
);*/

//Verifica se encontrou o usuário.
/*if(!$resultado || pg_num_rows($resultado) == 0 ) {
    die("Usuário não encontrado.");
}*/

//Pega os dados do banco
//$dadosUsuario = pg_fetch_assoc($resultado);

//Guarda cada informação em uma variável.
/*$nome = $dadosUsuario["nome"];
$email = $dadosUsuario["email"];
$senha = $dadosUsuario["senha"];*/

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
        <div class="container">
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
                
                <!--Botão Sair-->
                <br>
                <form method="get">
                    <button type="submit" name="sair" value="1">SAIR</button>
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

            <!--Área de Serviços-->
            <hr>
            <h2>Serviços</h2>
            <!--Botão Solicitar Novo Serviço-->
            <form method="get">
                <button type="submit" name="novo_servico" value=1>SOLICITAR NOVO SERVIÇO</button>
            </form>

            <!--FORMULÁRIO PARA SOLICITAR NOVO SERVIÇO
            Esse formulário aparece somente quando o usuário
            clica em SOLICITAR NOVO SERVIÇO.
            -->
            <?php if(isset($_GET["novo_servico"])): ?>
                <div>
                    <h2>Solicitar Novo Serviço</h2>
                    <form method="post">
                        <!--Descrição do Serviço-->
                        <div class="input-group">
                            <label for="descricao">Descrição do Serviço</label>
                            <textarea name="descricao" id="descricao" maxlenght="200" rows="5" placeholder="Digite a descrição" required></textarea>
                        </div>
                        <!--BOTÃO SOLICITAR-->
                        <button type="submit" name="solicitar_servico" value="1">SOLICITAR SERVIÇO</button>
                        <!--Botão CANCELAR SOLICITAÇÃO-->
                        <a href="area_cliente.php">
                            <button type="button">CANCELAR</button>
                        </a>
                    </form>
                </div>
            <?php else: ?>
                <!--TABELA DE SERVIÇOS
                Ela não aparece enquanto o formulário de novo
                serviço estiver aberto.
                -->
                <div>
                    <h2>Meus Serviços</h2>
                    <table class="tabela-servicos">
                        <tr>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>

                        <?php if(pg_num_rows($resultadoServicos) > 0): ?>
                            <?php while($servico = pg_fetch_assoc($resultadoServicos)): ?>
                                <tr>
                                    <!--DESCRIÇÃo-->
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $servico["descricao"]
                                        );
                                        ?>
                                    </td>

                                    <!--STATUS-->
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $servico["status"]
                                        );
                                        ?>
                                    </td>

                                    <!--Botão cancelar-->
                                    <td>
                                        <?php
                                        /*O botão de cancelar
                                        aparece somente se o
                                        serviço não estiver cancelado.
                                        */
                                        if($servico["status"] !== "CANCELADO"):
                                        ?>

                                        <form method="post">
                                            <!--Envia o ID do serviço para
                                            o PHP.
                                            Esse ID será usado para saber qual
                                            serviço deve ser cancelado.
                                            -->
                                            <input type="hidden" name="id_servico" value="<?php echo $servico["id_servicos"]; ?>">
                                            <button type="submit" name="cancelar_servico" value="1">CANCELAR</button>
                                        </form>

                                        <?php else: ?>
                                            <!--Se já estiver
                                            CANCELADO, não mostrar
                                            o botão novamente.
                                            -->
                                            <span>Serviço cancelado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <!--Caso o usuário ainda não tenha solicitado
                            nenhum serviço.
                            -->
                            <tr>
                                <td colspan="3">
                                    Nenhum serviço solicitado.
                                </td>
                            </tr>
                            <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </body>
</html>
