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

//Conexão com o Supabase - site para guardar os arquivos das solicitações de serviços.
$supabaseUrl = getenv("SUPABASE_URL");
$supabaseKey = getenv("SUPABASE_SERVICE_ROLE_KEY");

//Pega o usuário que está logado.
$usuarioLogado = $_SESSION["email"];

//Busca os dados do usuario
/*Busca os dados do usuário no banco. Pegamos o nome, email,
senha, e agora pegamos o id_usuarios.
Esse ID será utilizado para relazionar o usuário aos serviços
*/
$resultado = pg_query_params(
    $conexao,
    "SELECT id_usuarios, nome, email, senha, telefone FROM usuarios WHERE email = $1 AND tipo_usuario = 'CLIENTE' ",
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
//Guarda o Telefone
$telefone = $dadosUsuario["telefone"];

//Verifica se o formulário de edição foi enviado
//Salvar alterações
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["salvar"]) ) {
    //Pega os novos dados enviados pelo formulários
    $novoNome = $_POST["nome"];
    $novoEmail = $_POST["email"];
    $novaSenha = $_POST["senha"];
    $novoTelefone = $_POST["telefone"];

    //Se o telefone estiver vazio, mantem o telefone que já está cadastrado.
    if($novoTelefone == ""){
        $novoTelefone = $telefone;
    }

    //Se a senha estiver vazia, mantém a senha antiga.
    if($novaSenha == "") {
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios SET nome = $1, email = $2, telefone = $3 WHERE email = $4 AND tipo_usuario = 'CLIENTE' ",
            array($novoNome, $novoEmail, $novoTelefone, $usuarioLogado)
        );
    }else{
        //Se o usuário digitou uma senha, atualiza também a senha.
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios SET nome = $1, email = $2, senha = $3, telefone = $4 WHERE email = $5 AND tipo_usuario = 'CLIENTE'",
            array($novoNome, $novoEmail, $novaSenha, $novoTelefone, $usuarioLogado)
        );
    }

    //Verifica se a Alteração funcionou
    if($resultado) {
        //Atualiza o nome armazenado na sessão.
        $_SESSION["usuario"] = $novoNome;

        //Atualiza o email armazenado na sessão
        $_SESSION["email"] = $novoEmail;

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
    //$descricao = $_POST["descricao"];
    $descricao = trim($_POST["descricao"] ?? "");
    //O novo serviço começa com o status SOLICITADO
    $status = "SOLICITADO";

    //Verifica se a descrição foi preenchida
    if($descricao == ""){
        die("Digite a descrição do serviço.");
    }

    //Verifica a configuração do Supabase
    if(!$supabaseUrl || !$supabaseKey){
        die("Erro: configuração do Supabase não encontrada.");
    }

    //Prepara a lista de arquivos enviados.
    $arquivos = array();

    /*Aparentemente é um comadno IF grande, mas pelo visto
    é pra verificar ou processar os arquivos que foram
    anexados.*/
    if(isset($_FILES["anexos"])){
        $totalArquivos = count($_FILES["anexos"]["name"]);

        //Limita a quantidade de arquivos por solicitação.
        if($totalArquivos > 5){
            die("Você pode enviar no máximo 5 arquivos por serviço.");
        }

        //Verifica cada arquivo antes de enviar
        for($i = 0;$i < $totalArquivos; $i++){
            //Ignora se o cliente não selecionou um arquivo
            if($_FILES["anexos"]["error"][$i] == UPLOAD_ERR_NO_FILE){
                continue;
            }

            //Verifica se houve erro no envio
            if($_FILES["anexos"]["error"][$i] != UPLOAD_ERR_OK){
                die("Erro ao receber um dos arquivos.");
            }

            $arquivoTemporario = $_FILES["anexos"]["tmp_name"][$i];
            $nomeOriginal = basename($_FILES["anexos"]["name"][$i]);
            $tamanho = $_FILES["anexos"]["size"][$i];

            //Limita cada arquivo a 10 MB.
            if($tamanho <= 0 || $tamanho > 10 * 1024 * 1024){
                die("Cada barquivo deve ter no máximo 10 MB.");
            }

            //Verifica o tipo real do arquivo.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $tipoArquivo = finfo_file($finfo, $arquivoTemporario);
            finfo_close($finfo);

            //Aceita somente os tipos abaixo
            $tiposPermitidos = array(
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp",
                "application/pdf" => "pdf"
            );

            if(!isset($tiposPermitidos[$tipoArquivo])){
                die("Tipo de arquivo não permitido. Envie imagem JPG, PNG, WebP ou PDF.");
            }

            //Guarda os dados do arquivo validado
            $arquivos[] = array(
                "temporario" => $arquivoTemporario,
                "nome_original" => $nomeOriginal,
                "tamanho" => $tamanho,
                "tipo" => $tipoArquivo,
                "extensao" => $tiposPermitidos[$tipoArquivo]
            );
        }
    }

    /*Inseri o serviço no banco de dados
    id_usuario recebe o ID do usuário logado
    descricao recebe o texto digitado.
    status recebe SOLICITADO.
    */
    $resultado = pg_query_params(
        $conexao,
        "INSERT INTO servicos (descricao, status, id_usuario) VALUES ($1, $2, $3) RETURNING id_servicos",
        array($descricao, $status, $idUsuario)
    );

    if(!$resultado){
        die("Erro ao cadastrar o serviço no banco de dados.");
    }

    $dadosServico = pg_fetch_assoc($resultado);
    $idServico = $dadosServico["id_servicos"];

    //Guarda os caminhos dos arquivos enviados
    $arquivosEnviados = array();

    //Envia cada arquivo apra o Supabase
    foreach($arquivos as $arquivo){
        //Cria um nome único para evitar nomes repetidos
        $nomeArmazenado = bin2hex(random_bytes(16))
            . "." .$arquivo["extensao"];

        //Organiza os arquivos por usuário e serviço
        $caminhoArquivo = $idUsuario . "/"
            . $idServico . "/" . $nomeArmazenado;

        $urlUpload = rtrim($supabaseUrl, "/")
            . "/storage/v1/object/anexo-servicos/"
            . $caminhoArquivo;

        //Lê o conteúdo do arquivo temporário
        $conteudoArquivo = file_get_contents($arquivo["temporario"]);

        if($conteudoArquivo === false){
            $uploadFuncionou = false;
        }else{
            //Envia o arquivo ao SUpabase usando cURL
            $curl = curl_init($urlUpload);

            curl_setopt_array($curl, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => array(
                    "apikey: " . $supabaseKey,
                    "Authorization: Bearer " . $supabaseKey,
                    "Content-Type: " . $arquivo["tipo"],
                    "x-upsert: false"
                ),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120
            ));

            $respostaSupabase = curl_exec($curl);
            $codigoHTTP = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $erroCurl = curl_error($curl);

            curl_close($curl);

            $uploadFuncionou = (
                $respostaSupabase !== false &&
                $codigoHTTP >= 200 &&
                $codigoHTTP < 300
            );
        }

        //Se o envio falhar, interrompe o processo
        if(!$uploadFuncionou){
            //Tenta apagar os arquivos já que foram enviados
            foreach($arquivosEnviados as $caminhoAnterior){
                $urlExcluir = rtrim($supabaseUrl, "/")
                    . "/storage/v1/object/anexo-servicos/"
                    . $caminhoAnterior;
                
                    $curlExcluir = curl_init($urlExcluir);

                    curl_setopt_array($curlExcluir, array(
                        CURLOPT_CUSTOMREQUEST => "DELETE",
                        CURLOPT_HTTPHEADER => array(
                            "apikey: " . $supabaseKey,
                            "Authorization: Bearer " . $supabaseKey
                        ),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30
                    ));

                    curl_exec($curlExcluir);
                    curl_close($curlExcluir);
            }

            //Remove o serviço criado, pois o envio falho
            pg_query_params(
                $conexao,
                "DELETE FROM servicos WHERE id_servicos = $1 AND id_usuario = $2",
                array($idServico, $idUsuario)
            );

            error_log(
                "Falha no upload Supabase. HTTP: "
                . ($codigoHTTP ?? 0)
                . ". cURL: "
                . ($erroCurl ?? "")
            );

            die("Não foi possível enviar um dos arquivos. Verifique o arquivo e tente novamente.");   
        }

        //Guarda o caminho do arquivo para o banco de dados
        $arquivosEnviados[] = $caminhoArquivo;

        //Registra o anexo no Neon
        $resultadoAnexo = pg_query_params(
            $conexao,
            "INSERT INTO anexos_servicos (id_servico, nome_arquivo, caminho_arquivo) VALUES ($1, $2, $3)",
            array($idServico, $arquivo["nome_original"], $caminhoArquivo)
        );

        if(!$resultadoAnexo){
            error_log("Falha ao registrar o anexo no Neon para o serviço " . $idServico);
            die("O arquivo foi enviado, mas ocorreu um erro ao registrar o anexo no banco de dados. Entre em contato com o administrador.");
        }
    }

    //Serviço e anexos processados
    header("Location: area_cliente.php");
    exit;

    //Verifica se o cadastro funcionou
    //if($resultado) {
        /*Volta para a página
        Isso faz com que oformulário desapareça
        e a tabela de serviços seja mostrada novamente
        */
        /*header("Location: area_cliente.php");
        exit;
    }else{
        echo "<p>Erro ao solicitar o serviço.</p>";
    }*/
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

//Busca os anexos dos serviços do usuário
$resultadoAnexos = pg_query_params(
    $conexao,
    "SELECT anexos_servicos.id_servico,
            anexos_servicos.nome_arquivo,
            anexos_servicos.caminho_arquivo
    FROM anexos_servicos
    INNER JOIN servicos
        ON anexos_servicos.id_servico = servicos.id_servicos
    WHERE servicos.id_usuario = $1
    ORDER BY anexos_servicos.id_anexo",
    array($idUsuario)
);

//Verifica se a busca dos anexos funcionou
if(!$resultadoAnexos){
    die("Erro ao buscar os anexos dos serviços.");
}

//Guarda os anexos separados pelo ID do serviço
$anexoPorServico = array();

while($anexo = pg_fetch_assoc($resultadoAnexos)){
    //Cria o endereço para solicitar uma URL temporária
    $urlAssinatura = rtrim($supabaseUrl, "/")
        . "/storage/v1/object/sign/anexo-servicos/"
        . $anexo["caminho_arquivo"];
    
    //Solicita uma URL válida por uma hora
    $curl = curl_init($urlAssinatura);

    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            "apikey: " . $supabaseKey,
            "Authorization: Bearer " . $supabaseKey,
            "Content-Type: application/join"
        ),
        CURLOPT_POSTFIELDS => json_encode(
            array("expiresIn" => 3600)
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30
    ));

    $respostaSupabase = curl_exec($curl);
    $codigoHTTP = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    //Converte a resposta do Supabase
    $dadosAssinatura = json_decode($respostaSupabase, true);

    //Verifica se a URL foi criada
    if($codigoHTTP >= 200 && $codigoHTTP < 300 && isset($dadosAssinatura["signedURL"])){
        $urlArquivo = $dadosAssinatura["signedURL"];

        //Se o endereço foi relativo, acrescenta a URL do projeto
        if(strpos($urlArquivo, "http") !== 0){
            $urlArquivo = rtrim($supabaseUrl, "/")
                . "/storage/v1" . $urlArquivo;
        }

        //Guarda os dados do anexo pelo ID do servico
        $anexoPorServico[$anexo["id_servico"]][] = array(
            "nome_arquivo" => $anexo["nome_arquivo"],
            "url" => $urlArquivo
        );
    }
}

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

                    <!--ADICIONEI O CAMPO DO TELEFONE-->
                    <tr>
                        <td>Telefone</td>
                        <td>
                            <?php echo htmlspecialchars($telefone ?? ""); ?>
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

                    <!--TELEFONE-->
                    <div class="input-group">
                        <label for="telefone">Telefone:</label>
                        <input type="tel" name="telefone" id="telefone" value=" <?php echo htmlspecialchars($telefone ?? ""); ?> " placeholder="Digite seu telefone" >
                        <small>Deixe vazio para manter o telefone atual.</small>
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
                    <form method="post" enctype="multipart/form-data">
                        <!--Descrição do Serviço-->
                        <div class="input-group">
                            <label for="descricao">Descrição do Serviço</label>
                            <textarea name="descricao" id="descricao" maxlength="200" rows="5" placeholder="Digite a descrição" required></textarea>
                        </div>
                        <!--PARTE PARA ANEXAR Imagens ou PDFs-->
                        <div class="input-group">
                            <label for="anexos">Anexar imagens ou PDFs:</label>
                            <input type="file" name="anexos[]" id="anexos" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
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
                            <th>Anexos</th>
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

                                    <!--ANEXOS DOS SERVIÇOS-->
                                    <td>
                                        <?php
                                        //Verifica se o serviço possui anexos
                                        if(isset($anexoPorServico[$servico["id_servicos"]])):
                                            //Exibe cada anexo do serviço
                                            foreach($anexoPorServico[$servico["id_servicos"]] as $anexo):
                                        ?>
                                            <a href="<?php echo htmlspecialchars($anexo["url"], ENT_QUOTES, "UTF-8");?>"
                                                target="_blank"
                                                rel="noopener noreferrer">
                                                <?php echo htmlspecialchars($anexo["nome_arquivo"], ENT_QUOTES, "UTF-8");?>
                                            </a>
                                            <br>
                                        <?php
                                            endforeach;
                                        else:
                                        ?>
                                            Nenhum anexo.
                                        <?php endif; ?>
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
                                <td colspan="4">
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
