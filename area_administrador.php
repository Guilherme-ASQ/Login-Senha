<?php
    session_start();
    //LOGOUT
    //Verifica se o administrador clicou no botão SAIR.
    if(isset($_GET["sair"])){
        //Destrói a sessão
        session_unset();
        session_destroy();

        //Volta para a página inicial
        header("Location: index.php");
        exit;
    }

    //VERIFICA SE O USUÁRIO ESTÁ LOGADO
    if(!isset($_SESSION["usuario"])){
        header("Location: index.php");
        exit;
    }

    //VERIFICA SE O USUÁRIO É ADMINISTRADOR
    if(!isset($_SESSION["tipo_usuario"]) || $_SESSION["tipo_usuario"] !== "ADMINISTRADOR"){
        header("Location: index.php");
        exit;
    }

    //CONEXÃO COM O BANCO DE DADOS
    $dataBaseUrl = getenv("DATABASE_URL");
    $conexao = pg_connect($dataBaseUrl);
    if(!$conexao){
        die("Erro ao conectar ao Banco de Dados.");
    }

    //PEGA O USUÁRIO QUE ESTÁ LOGADO
    $usuarioLogado = $_SESSION["email"];

    //BUSCA OS DADOS DO ADMINISTRADOR
    $resultado = pg_query_params(
        $conexao,
        "SELECT id_usuarios, nome, email, senha FROM usuarios WHERE email = $1 AND tipo_usuario = 'ADMINISTRADOR'",
        array($usuarioLogado)
    );

    //Verifica se o usuário foi encontrado.
    if(!$resultado || pg_num_rows($resultado) == 0){
        die("Usuário não encontrado.");
    }

    //Guarda os dados do usuário.
    $dadosUsuario = pg_fetch_assoc($resultado);
    //Guarda cada informação em uma variável.
    $idUsuario = $dadosUsuario["id_usuarios"];
    $nome = $dadosUsuario["nome"];
    $email = $dadosUsuario["email"];
    $senha = $dadosUsuario["senha"];

    //ALTERAR STATUS DOS SERVIÇOS
    //Verifica se o administrador clicou em um botão para alterar o status de um serviço.
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["alterar_status"])){
        //Pega o ID do serviço.
        $idServico = $_POST["id_servico"] ?? "";
        //Pega o novo status enviado pelo botão.
        $novoStatus = $_POST["novo_status"] ?? "";

        //Define quais mudanças de status são permitidas.
        //O índice representa o NOVO status. O valor representa o STATUS ATUAL obrigatório.
        $mudancasPermitidas = array(
            "EM_ANALISE" => "SOLICITADO",
            "CONCLUIDO" => "EM_ANALISE",
            "CANCELADO" => "EM_ANALISE"
        );

        //Verifica se o ID é válido e se a mudança solicitada está na lista de mudanças permitidas.
        if(filter_var($idServico, FILTER_VALIDATE_INT) !== false && (int)$idServico > 0 && isset($mudancasPermitidas[$novoStatus])){
            //Descobre qual deve ser o status atual.
            $statusAtual = $mudancasPermitidas[$novoStatus];
            //Atualiza o status somente se o serviço ainda estiver no status atual esperado.
            //Exemplo: Para mudar para EM_ANALISE, o status atual obrigatoriamente precisa ser SOLICITADO.
            $resultadoStatus = pg_query_params(
                $conexao,
                "UPDATE servicos SET status = $1 WHERE id_servicos = $2 AND status = $3",
                array($novoStatus, (int)$idServico, $statusAtual)
            );

            //Verifica se o comando SQL funcionou.
            if(!$resultadoStatus){
                die("Erro ao laterar o status do serviço.");
            }
            //Volta para a página. As tabelas serão carregadas novamente com os status atualizados.
            header("Location: area_administrador.php");
            exit;

        }else{
            //Impede mudanças de status não permitidas.
            die("Alteração de status inválida.");
        }
    }

    //ALTERAR INFORMAÇÕES DO ADMINISTRADOR
    if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["salvar"])){
        //Pega os novos dados enviados pelo formulário
        $novoNome = $_POST["nome"];
        $novoEmail = $_POST["email"];
        $novaSenha = $_POST["senha"];

        //Se a senha estiver vazia, mantém a senha atual.
        if($novaSenha == ""){
            $resultado = pg_query_params(
                $conexao,
                "UPDATE usuarios SET nome = $1, email = $2 WHERE id_usuarios = $3 AND tipo_usuario = 'ADMINISTRADOR'",
                array($novoNome, $novoEmail, $idUsuario)
            );
        }else{
            //Atualiza a senha se o usuário digitou uma nova.
            $resultado = pg_query_params(
                $conexao,
                "UPDATE usuarios SET nome = $1, email = $2, senha = $3 WHERE id_usuarios = $4 AND tipo_usuario = 'ADMINISTRADOR'",
                array($novoNome, $novoEmail, $novaSenha, $idUsuario)
            );
        }

        //Verifica se a alteração funcionou.
        if($resultado){
            //Atualiza os dados guardados na sessão.
            $_SESSION["usuario"] = $novoNome;
            $_SESSION["email"] = $novoEmail;
            //Atualiza a página
            header("Location: area_administrador.php");
            exit;
        }else{
            echo "<p>Erro ao atualizar as informações.</p>";
        }
    }

    //BUSCA OS SERVIÇOS SOLICITADOS
    $resultadoSolicitados = pg_query(
        $conexao,
        "SELECT
            servicos.id_servicos AS id_servico,
            usuarios.nome AS nome,
            usuarios.email AS email,
            servicos.descricao AS solicitacao
        FROM usuarios
        INNER JOIN servicos
            ON servicos.id_usuario = usuarios.id_usuarios
        WHERE usuarios.tipo_usuario = 'CLIENTE'
            AND servicos.status = 'SOLICITADO'
        ORDER BY servicos.id_servicos ASC"
    );

    if(!$resultadoSolicitados){
        die("Erro ao buscar os serviços solicitados.");
    }

    //BUSCAR OS SERVIÇOS EM ANÁLISE
    $resultadoAnalise = pg_query(
        $conexao,
        "SELECT
            servicos.id_servicos AS id_servico,
            usuarios.nome AS nome,
            usuarios.email AS email,
            servicos.descricao AS solicitacao
        FROM usuarios
        INNER JOIN servicos
            ON servicos.id_usuario = usuarios.id_usuarios
        WHERE usuarios.tipo_usuario = 'CLIENTE'
            AND servicos.status = 'EM_ANALISE'
        ORDER BY servicos.id_servicos ASC"
    );

    if(!$resultadoAnalise){
        die("Erro ao buscar os serviços em análise.");
    }

    //BUSCAR OS SERVIÇOS CONCLUÍDOS
    $resultadoConcluidos = pg_query(
        $conexao,
        "SELECT
            servicos.id_servicos AS id_servico,
            usuarios.nome AS nome,
            usuarios.email AS email,
            servicos.descricao AS solicitacao
            FROM usuarios
            INNER JOIN servicos
                ON servicos.id_usuario = usuarios.id_usuarios
            WHERE usuarios.tipo_usuario = 'CLIENTE'
                AND servicos.status = 'CONCLUIDO'
            ORDER BY servicos.id_servicos ASC"
    );

    if(!$resultadoConcluidos){
        die("Erro ao buscar os serviços concluídos.");
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

            <!-- ======================================
             GERENCIAMENTO DE SERVIÇOS
            ======================================= -->
            <hr>
            <h1>Gerenciamento de Serviços</h1>
            <div class="painel-servicos">
                <!-- ======================================
                TABELA DA ESQUERDA: SOLICITADO
                ======================================= -->
                <div class="coluna-servicos">
                    <h2 class="status-titulo">SOLICITADO</h2>
                    <table class="tabela-servicos">
                        <tr>
                            <th>Cliente e Solicitação</th>
                        </tr>
                        <?php if(pg_num_rows($resultadoSolicitados) > 0): ?>
                            <?php while($idServico = pg_fetch_assoc($resultadoSolicitados)): ?>
                                <tr>
                                    <td>
                                        <strong>Nome:</strong><br>
                                        <?php echo htmlspecialchars($servico["nome"]); ?>
                                        
                                        <br><br>

                                        <strong>Email:</strong><br>
                                        <?php echo htmlspecialchars($servico["email"]); ?>
                                        
                                        <br><br>

                                        <strong>Solicitação:</strong><br>
                                        <?php echo htmlspecialchars($servico["solicitacao"]); ?>

                                        <!-- Botão: SOLICITADA para EM_ANALISE -->
                                        <form method="post">
                                            <input type="hidden" name="id_servico" value="<?php echo (int)$servico["id_servico"]; ?>">
                                            
                                            <input type="hidden" name="novo_status" value="EM_ANALISE">

                                            <button type="submit" name="alterar_status" value="1">INICIAR ANÁLISE</button>
                                        </form>

                                    </td><!--FIM DA td-->
                                </tr><!--FIM DA tr-->

                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td>Nenhum serviço solicitado.</td>
                                </tr>
                            <?php endif; ?>

                    </table><!--FIM DA TABLE-->
                </div><!--FIM da div da tabela dos serviços soliditados-->

                <!-- ======================================
                TABELA DA MEIO: EM_ANALISE
                ======================================= -->

                <div class="coluna-servicos">
                    <h2 class="status-titulo">EM ANÁLISE</h2>
                    <table class="tabela-servicos">
                        <tr>
                            <th>Cliente e Solicitação</th>
                        </tr>

                        <?php if(pg_num_rows($resultadoAnalise) > 0): ?>
                            <?php while($servico = pg_fetch_assoc($resultadoAnalise)): ?>
                                <tr>
                                    <td>
                                        <strong>Nome:</strong><br>
                                        <?php echo htmlspecialchars($servico["nome"]); ?>

                                        <br><br>

                                        <strong>Email:</strong><br>
                                        <?php echo htmlspecialchars($servico["email"]); ?>

                                        <br><br>

                                        <strong>Solicitação:</strong><br>
                                        <?php echo htmlspecialchars($servico["solicitacao"]); ?>

                                        <!--Botão: EM_ANALISE para CONCLUIDO-->
                                        <form method="post">
                                            <input type="hidden" name="id_servico" value="<?php echo (int)$servico["id_servico"]; ?>">

                                            <input type="hidden" name="novo_status" value="CONCLUIDO">

                                            <button type="submit" name="alterar_status" value="1">CONCLUIR SERVIÇO</button>
                                        </form><!--FIM do forms-->

                                        <!--Botão: EM_ANALISE para CANCELADO-->
                                        <form method="post">
                                            <input type="hidden" name="id_servico" value="<?php echo (int)$servico["id_servico"]; ?>">

                                            <input type="hidden" name="novo_status" value="CANCELADO">

                                            <button type="submit" name="alterar-status" value="1">CANCELAR SERVIÇO</button>
                                        </form><!--Fim do forms-->
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td>Nenhum serviço em análise.</td>
                            </tr>
                        <?php endif; ?>
                    </table><!--FIM DA TABELA-->
                </div><!--FIM DA DIV DA TABELA DOS SERVIÇOS EM_ANALISE-->

                <!-- ======================================
                TABELA DA DIREITA: CONCLUIDO
                ======================================= -->
                <div class="coluna-servicos">
                    <h2 class="status-titulo">CONCLUÍDO</h2>
                    <table class="tabela-servicos">
                        <tr>
                            <th>Cliente e Solicitação</th>
                        </tr>

                        <?php if(pg_num_rows($resultadoConcluidos) > 0): ?>
                            <?php while($servico = pg_fetch_assoc($resultadoConcluidos)): ?>
                                <tr>
                                    <td>
                                        <strong>Nome:</strong><br>
                                        <?php echo htmlspecialchars($servico["nome"]); ?>

                                        <br><br>

                                        <strong>Email:</strong><br>
                                        <?php echo htmlspecialchars($servico["email"]); ?>

                                        <br><br>

                                        <strong>Status:</strong> CONCLUIDO
                                        <!--
                                        Não há botão nesta tabela. Um serviço concluído não pode ter seu status alterado.
                                        -->
                                    </td>
                                </tr>

                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td>Nenhum serviço concluído.</td>
                                </tr>
                            <?php endif; ?>
                    </table><!--FIM da tabela da Direita-->
                </div><!--FIM DA DIV COM A TABELA-->

            </div><!--FIM DA DIV painel-servicos-->

        </div>
    </body>
</html>
