<?php
// Inicia a sessão
session_start();

// Verifica se o usuário clicou no botão SAIR
if (isset($_GET["sair"])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Verifica se existe um usuário logado
if (!isset($_SESSION["usuario"])) {
    header("Location: index.php");
    exit;
}

// Verifica se o usuário é CLIENTE
if (
    !isset($_SESSION["tipo_usuario"]) ||
    $_SESSION["tipo_usuario"] !== "CLIENTE"
) {
    header("Location: index.php");
    exit;
}

// Conexão com o banco de dados Neon
$databaseUrl = getenv("DATABASE_URL");
$conexao = pg_connect($databaseUrl);

if (!$conexao) {
    die("Erro ao conectar ao banco de dados.");
}

// Conexão com o Supabase
$supabaseUrl = getenv("SUPABASE_URL");
$supabaseKey = getenv("SUPABASE_SERVICE_ROLE_KEY");

// Nome do bucket no Supabase.
// Confirme se o nome está exatamente igual ao do painel.
$nomeBucket = "anexo-servicos";

// Pega o e-mail do usuário logado
$usuarioLogado = $_SESSION["email"];

// Busca os dados do usuário
$resultado = pg_query_params(
    $conexao,
    "SELECT id_usuarios, nome, email, senha, telefone
     FROM usuarios
     WHERE email = $1 AND tipo_usuario = 'CLIENTE'",
    array($usuarioLogado)
);

// Verifica se encontrou o usuário
if (!$resultado || pg_num_rows($resultado) == 0) {
    die("Usuário não encontrado.");
}

// Guarda os dados do usuário
$dadosUsuario = pg_fetch_assoc($resultado);

$idUsuario = $dadosUsuario["id_usuarios"];
$nome = $dadosUsuario["nome"];
$email = $dadosUsuario["email"];
$senha = $dadosUsuario["senha"];
$telefone = $dadosUsuario["telefone"];

// ==================================================
// EDITAR OS DADOS DO CLIENTE
// ==================================================

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["salvar"])
) {
    $novoNome = trim($_POST["nome"] ?? "");
    $novoEmail = trim($_POST["email"] ?? "");
    $novaSenha = $_POST["senha"] ?? "";
    $novoTelefone = trim($_POST["telefone"] ?? "");

    // Verifica se os campos obrigatórios foram preenchidos
    if ($novoNome == "" || $novoEmail == "") {
        die("Preencha o nome e o e-mail.");
    }

    // Se o telefone estiver vazio, mantém o telefone antigo
    if ($novoTelefone == "") {
        $novoTelefone = $telefone;
    }

    // Se a senha estiver vazia, mantém a senha antiga
    if ($novaSenha == "") {
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios
             SET nome = $1, email = $2, telefone = $3
             WHERE id_usuarios = $4
               AND tipo_usuario = 'CLIENTE'",
            array(
                $novoNome,
                $novoEmail,
                $novoTelefone,
                $idUsuario
            )
        );
    } else {
        // Se uma nova senha foi digitada, atualiza também a senha
        $resultado = pg_query_params(
            $conexao,
            "UPDATE usuarios
             SET nome = $1, email = $2, senha = $3, telefone = $4
             WHERE id_usuarios = $5
               AND tipo_usuario = 'CLIENTE'",
            array(
                $novoNome,
                $novoEmail,
                $novaSenha,
                $novoTelefone,
                $idUsuario
            )
        );
    }

    // Verifica se a alteração funcionou
    if ($resultado) {
        $_SESSION["usuario"] = $novoNome;
        $_SESSION["email"] = $novoEmail;

        header("Location: area_cliente.php");
        exit;
    } else {
        error_log("Erro ao atualizar os dados do cliente.");
        echo "<p>Erro ao atualizar as informações. Verifique se o nome e o e-mail já estão cadastrados.</p>";
    }
}

// ==================================================
// SOLICITAR UM NOVO SERVIÇO
// ==================================================

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["solicitar_servico"])
) {
    // Pega a descrição digitada pelo cliente
    $descricao = trim($_POST["descricao"] ?? "");

    // O serviço começa com o status SOLICITADO
    $status = "SOLICITADO";

    if ($descricao == "") {
        die("Digite a descrição do serviço.");
    }

    // Verifica a configuração do Supabase
    if (
        !$supabaseUrl ||
        !$supabaseKey
    ) {
        error_log("SUPABASE_URL ou SUPABASE_SERVICE_ROLE_KEY não configurada.");
        die("Erro: configuração do Supabase não encontrada.");
    }

    // Lista os arquivos que passaram pela validação
    $arquivos = array();

    // Verifica se foram enviados anexos
    if (isset($_FILES["anexos"])) {
        $totalArquivos = count($_FILES["anexos"]["name"]);

        // Limita a quantidade de arquivos por serviço
        $quantidadeSelecionada = 0;

        for ($i = 0; $i < $totalArquivos; $i++) {
            if ($_FILES["anexos"]["error"][$i] != UPLOAD_ERR_NO_FILE) {
                $quantidadeSelecionada++;
            }
        }

        if ($quantidadeSelecionada > 5) {
            die("Você pode enviar no máximo 5 arquivos por serviço.");
        }

        // Verifica cada arquivo
        for ($i = 0; $i < $totalArquivos; $i++) {
            $erroUpload = $_FILES["anexos"]["error"][$i];

            // Ignora campos de arquivo que ficaram vazios
            if ($erroUpload == UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Verifica se houve erro no recebimento do arquivo
            if ($erroUpload != UPLOAD_ERR_OK) {
                die("Erro ao receber um dos arquivos.");
            }

            $arquivoTemporario = $_FILES["anexos"]["tmp_name"][$i];
            $nomeOriginal = basename($_FILES["anexos"]["name"][$i]);
            $tamanho = $_FILES["anexos"]["size"][$i];

            // Limita cada arquivo a 10 MB
            if ($tamanho <= 0 || $tamanho > 10 * 1024 * 1024) {
                die("Cada arquivo deve ter mais de 0 bytes e no máximo 10 MB.");
            }

            // Verifica o tipo real do arquivo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo === false) {
                error_log("Não foi possível iniciar a verificação do tipo do arquivo.");
                die("Não foi possível verificar um dos arquivos.");
            }

            $tipoArquivo = finfo_file($finfo, $arquivoTemporario);
            finfo_close($finfo);

            // Tipos de arquivo permitidos
            $tiposPermitidos = array(
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp",
                "application/pdf" => "pdf"
            );

            if (!isset($tiposPermitidos[$tipoArquivo])) {
                die("Tipo de arquivo não permitido. Envie imagem JPG, PNG, WebP ou PDF.");
            }

            // Guarda os dados do arquivo validado
            $arquivos[] = array(
                "temporario" => $arquivoTemporario,
                "nome_original" => $nomeOriginal,
                "tamanho" => $tamanho,
                "tipo" => $tipoArquivo,
                "extensao" => $tiposPermitidos[$tipoArquivo]
            );
        }
    }

    // Cadastra o serviço no Neon
    $resultado = pg_query_params(
        $conexao,
        "INSERT INTO servicos (descricao, status, id_usuario)
         VALUES ($1, $2, $3)
         RETURNING id_servicos",
        array($descricao, $status, $idUsuario)
    );

    if (!$resultado) {
        error_log("Erro ao cadastrar serviço no Neon.");
        die("Erro ao cadastrar o serviço no banco de dados.");
    }

    $dadosServico = pg_fetch_assoc($resultado);
    $idServico = $dadosServico["id_servicos"];

    // Guarda os caminhos dos arquivos enviados com sucesso
    $arquivosEnviados = array();

    // Envia cada arquivo para o Supabase
    foreach ($arquivos as $arquivo) {
        // Cria um nome único para evitar nomes repetidos
        $nomeArmazenado = bin2hex(random_bytes(16))
            . "." . $arquivo["extensao"];

        // Organiza os arquivos por usuário e serviço
        $caminhoArquivo = $idUsuario . "/"
            . $idServico . "/" . $nomeArmazenado;

        $urlUpload = rtrim($supabaseUrl, "/")
            . "/storage/v1/object/"
            . $nomeBucket . "/"
            . $caminhoArquivo;

        // Inicializa as variáveis de diagnóstico
        $uploadFuncionou = false;
        $codigoHTTP = 0;
        $erroCurl = "";
        $respostaSupabase = "";

        // Lê o conteúdo do arquivo temporário
        $conteudoArquivo = file_get_contents($arquivo["temporario"]);

        if ($conteudoArquivo === false) {
            $erroCurl = "Não foi possível ler o arquivo temporário.";
        } else {
            // Prepara o envio para o Supabase
            $curl = curl_init($urlUpload);

            if ($curl === false) {
                $erroCurl = "Não foi possível iniciar o cURL.";
            } else {
                curl_setopt_array($curl, array(
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => array(
                        "apikey: " . $supabaseKey,
                        "Authorization: Bearer " . $supabaseKey,
                        "Content-Type: " . $arquivo["tipo"],
                        "x-upsert: false"
                    ),
                    CURLOPT_POSTFIELDS => $conteudoArquivo,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_TIMEOUT => 120
                ));

                // Envia o arquivo
                $respostaSupabase = curl_exec($curl);

                // Guarda o código HTTP e o erro do cURL
                $codigoHTTP = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $erroCurl = curl_error($curl);

                // Considera o envio bem-sucedido somente se o HTTP for 2xx
                $uploadFuncionou = (
                    $respostaSupabase !== false &&
                    $codigoHTTP >= 200 &&
                    $codigoHTTP < 300
                );

                curl_close($curl);
            }
        }

        // Registra detalhes no log se o upload falhar
        if (!$uploadFuncionou) {
            error_log(
                "Falha no upload Supabase."
                . " | HTTP: " . $codigoHTTP
                . " | cURL: " . $erroCurl
                . " | Resposta: " . (
                    is_string($respostaSupabase)
                    ? $respostaSupabase
                    : ""
                )
            );

            // Tenta excluir os arquivos enviados anteriormente
            foreach ($arquivosEnviados as $caminhoAnterior) {
                $urlExcluir = rtrim($supabaseUrl, "/")
                    . "/storage/v1/object/"
                    . $nomeBucket . "/"
                    . $caminhoAnterior;

                $curlExcluir = curl_init($urlExcluir);

                if ($curlExcluir !== false) {
                    curl_setopt_array($curlExcluir, array(
                        CURLOPT_CUSTOMREQUEST => "DELETE",
                        CURLOPT_HTTPHEADER => array(
                            "apikey: " . $supabaseKey,
                            "Authorization: Bearer " . $supabaseKey
                        ),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CONNECTTIMEOUT => 15,
                        CURLOPT_TIMEOUT => 30
                    ));

                    curl_exec($curlExcluir);
                    curl_close($curlExcluir);
                }
            }

            // Remove os registros de anexos que possam ter sido criados
            pg_query_params(
                $conexao,
                "DELETE FROM anexos_servicos WHERE id_servico = $1",
                array($idServico)
            );

            // Remove o serviço criado, pois o upload falhou
            pg_query_params(
                $conexao,
                "DELETE FROM servicos
                 WHERE id_servicos = $1 AND id_usuario = $2",
                array($idServico, $idUsuario)
            );

            die("Não foi possível enviar um dos arquivos. Verifique o arquivo e tente novamente.");
        }

        // Guarda o caminho do arquivo enviado
        $arquivosEnviados[] = $caminhoArquivo;

        // Registra o anexo no Neon
        $resultadoAnexo = pg_query_params(
            $conexao,
            "INSERT INTO anexos_servicos
             (id_servico, nome_arquivo, caminho_arquivo)
             VALUES ($1, $2, $3)",
            array(
                $idServico,
                $arquivo["nome_original"],
                $caminhoArquivo
            )
        );

        // Se não conseguir registrar o anexo, registra o erro no log
        if (!$resultadoAnexo) {
            error_log(
                "Falha ao registrar o anexo no Neon."
                . " | Serviço: " . $idServico
                . " | Arquivo: " . $arquivo["nome_original"]
            );

            // Exclui os arquivos enviados ao Supabase
            foreach ($arquivosEnviados as $caminhoAnterior) {
                $urlExcluir = rtrim($supabaseUrl, "/")
                    . "/storage/v1/object/"
                    . $nomeBucket . "/"
                    . $caminhoAnterior;

                $curlExcluir = curl_init($urlExcluir);

                if ($curlExcluir !== false) {
                    curl_setopt_array($curlExcluir, array(
                        CURLOPT_CUSTOMREQUEST => "DELETE",
                        CURLOPT_HTTPHEADER => array(
                            "apikey: " . $supabaseKey,
                            "Authorization: Bearer " . $supabaseKey
                        ),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CONNECTTIMEOUT => 15,
                        CURLOPT_TIMEOUT => 30
                    ));

                    curl_exec($curlExcluir);
                    curl_close($curlExcluir);
                }
            }

            // Remove os registros de anexos e o serviço
            pg_query_params(
                $conexao,
                "DELETE FROM anexos_servicos WHERE id_servico = $1",
                array($idServico)
            );

            pg_query_params(
                $conexao,
                "DELETE FROM servicos
                 WHERE id_servicos = $1 AND id_usuario = $2",
                array($idServico, $idUsuario)
            );

            die("Ocorreu um erro ao registrar o anexo no banco de dados. Tente novamente.");
        }
    }

    // Serviço e anexos processados
    header("Location: area_cliente.php");
    exit;
}

// ==================================================
// CANCELAR UM SERVIÇO
// ==================================================

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["cancelar_servico"])
) {
    $idServicoCancelar = $_POST["id_servico"] ?? "";

    // Altera somente um serviço pertencente ao cliente logado
    $resultado = pg_query_params(
        $conexao,
        "UPDATE servicos
         SET status = 'CANCELADO'
         WHERE id_servicos = $1 AND id_usuario = $2",
        array($idServicoCancelar, $idUsuario)
    );

    if ($resultado) {
        header("Location: area_cliente.php");
        exit;
    } else {
        error_log("Erro ao cancelar serviço.");
        echo "<p>Erro ao cancelar o serviço.</p>";
    }
}

// ==================================================
// BUSCAR OS SERVIÇOS DO CLIENTE
// ==================================================

$resultadoServicos = pg_query_params(
    $conexao,
    "SELECT id_servicos, descricao, status
     FROM servicos
     WHERE id_usuario = $1
     ORDER BY id_servicos DESC",
    array($idUsuario)
);

if (!$resultadoServicos) {
    error_log("Erro ao buscar os serviços do cliente.");
    die("Erro ao buscar os serviços.");
}

// ==================================================
// BUSCAR OS ANEXOS DOS SERVIÇOS
// ==================================================

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

if (!$resultadoAnexos) {
    error_log("Erro ao buscar os anexos dos serviços.");
    die("Erro ao buscar os anexos dos serviços.");
}

// Guarda os anexos separados pelo ID do serviço
$anexoPorServico = array();

// Verifica a configuração do Supabase antes de gerar URLs
if (!$supabaseUrl || !$supabaseKey) {
    error_log("Configuração do Supabase ausente ao buscar anexos.");
} else {
    while ($anexo = pg_fetch_assoc($resultadoAnexos)) {
        // Prepara a URL para solicitar uma URL temporária
        $urlAssinatura = rtrim($supabaseUrl, "/")
            . "/storage/v1/object/sign/"
            . $nomeBucket . "/"
            . $anexo["caminho_arquivo"];

        // Inicializa variáveis de diagnóstico
        $respostaSupabase = false;
        $codigoHTTP = 0;
        $erroCurl = "";

        $curl = curl_init($urlAssinatura);

        if ($curl !== false) {
            curl_setopt_array($curl, array(
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => array(
                    "apikey: " . $supabaseKey,
                    "Authorization: Bearer " . $supabaseKey,
                    "Content-Type: application/json"
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
            $erroCurl = curl_error($curl);

            curl_close($curl);
        } else {
            $erroCurl = "Não foi possível iniciar o cURL para gerar a URL temporária.";
        }

        // Verifica se o Supabase retornou uma resposta válida
        $dadosAssinatura = is_string($respostaSupabase)
            ? json_decode($respostaSupabase, true)
            : null;

        if (
            $codigoHTTP >= 200 &&
            $codigoHTTP < 300 &&
            is_array($dadosAssinatura) &&
            isset($dadosAssinatura["signedURL"])
        ) {
            $urlArquivo = $dadosAssinatura["signedURL"];

            // Se o endereço for relativo, acrescenta a URL do projeto
            if (strpos($urlArquivo, "http") !== 0) {
                $urlArquivo = rtrim($supabaseUrl, "/")
                    . "/storage/v1" . $urlArquivo;
            }

            // Guarda o anexo pelo ID do serviço
            $anexoPorServico[$anexo["id_servico"]][] = array(
                "nome_arquivo" => $anexo["nome_arquivo"],
                "url" => $urlArquivo
            );
        } else {
            // Registra detalhes para ajudar a identificar o problema
            error_log(
                "Falha ao gerar URL temporária do Supabase."
                . " | HTTP: " . $codigoHTTP
                . " | cURL: " . $erroCurl
                . " | Resposta: " . (
                    is_string($respostaSupabase)
                    ? $respostaSupabase
                    : ""
                )
                . " | Caminho: " . $anexo["caminho_arquivo"]
            );
        }
    }
}

// FIM DO CÓDIGO PHP
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
