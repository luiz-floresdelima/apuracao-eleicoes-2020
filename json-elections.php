<?php

// Começo infos TSE
// $host = 'https://resultados.tse.jus.br/publico';
$host = 'https://resultados.tse.jus.br/oficial';
// $env = 'simulado';
$env = 'oficial';
$cicle = 'ele2020';
// $election = '8334';
$election = '426';
// Fim infos TSE

// Variável para ficar com 6 casas
$election_jus = sprintf("%06s", $election);

// Código dos cargos para busca (0001 => presidente;0003 => governador;0005 => senador;0006 => dep.fed;0007 => dep.est;0008 => dep.distr;0011 => prefeito;0013 => vereador;)
$cargos = array("0011" => "Prefeito", "0013" => "Vereador");

// Código das capitais de cada estado e entorno DF (Caso queira pegar todos, basta executar a função updateMunicipios())
$uf_muns = array(
    "ac" => array("01392"),
    "al" => array("27855"),
    "ap" => array("06050"),
    "am" => array("02550"),
    "ba" => array("38490"),
    "ce" => array("13897"),
    "es" => array("57053"),
    "go" => array("92010", "96938", "93343", "92053", "92118", "92150", "92630", "92797", "93050", "93025", "92886", "93173", "93254", "93599", "93610", "93718", "94455", "92347", "94897", "93327", "95095", "95435", "95958", "96776", "95974", "92720", "93300", "93289", "93505", "93734"),
    "ma" => array("09210"),
    "mt" => array("90670"),
    "ms" => array("90514"),
    "mg" => array("40894", "41858", "41785", "54070", "41238"),
    "pa" => array("04278"),
    "pb" => array("20516"),
    "pr" => array("75353"),
    "pe" => array("25313"),
    "pi" => array("12190"),
    "rj" => array("60011"),
    "rn" => array("17612"),
    "rs" => array("88013"),
    "ro" => array("00035"),
    "rr" => array("03018"),
    "sc" => array("81051"),
    "sp" => array("71072"),
    "se" => array("31054"),
    "to" => array("73440")
);

// Local onde colocar os arquivos tratados
// Conceder permissões de escrita nas pastas

// Local files
$base_files = "./files/";
$base_fotos = "./fotos/";

// Caso queira pegar o ciclo da eleição conforme arquivos, executar o seguinte código
function getCicle()
{
    global $env, $host, $cicle;

    $common_url = "{$host}/comum/divulgacao/{$env}/config/ele-c.json";
    $conteudo = json_decode(file_get_contents($common_url));
    $cicle = $conteudo->c;
}

// Pegar o arquivo de resultado consolidado
function getSimpleResults($uf, $cod_mun, $cod_cargo)
{
    global $env, $host, $cicle, $election, $election_jus, $base_files;
    $path = "{$host}/{$cicle}/divulgacao/{$env}/{$election}/dados-simplificados/";
    $documento_r = "{$path}{$uf}/{$uf}{$cod_mun}-c{$cod_cargo}-e{$election_jus}-r.json";
    $json_results = json_decode(file_get_contents($documento_r));
    return $json_results;
    // file_put_contents("{$base_files}{$uf}-{$cod_mun}-{$cod_cargo}.json", $json_results);
}

function getLastResultTSE($uf, $mun, $json_var, $json_fixo, $json_res)
{
    return array(
        "arquivo_fixo" => $json_var->nadf,
        "cidade" => $json_fixo->nmabr,
        "cod_municipio" => $mun,
        "total_apuracao" => $json_res->tv,
        "percentual_apuracao" => $json_res->pst,
        "total_votos_brancos" => $json_res->vb,
        "total_votos_nulos" => $json_res->tvn,
        "total_votos_validos" => $json_res->vv,
        "comparecimento" => $json_res->c,
        "abstencao" => $json_res->a,
    );
}

// Função para pegar todos os municípios
function updateMunicipios()
{
    global $env, $host, $cicle, $election, $election_jus, $uf_muns;
    $path = "{$host}/{$cicle}/divulgacao/{$env}/{$election}/config/";
    $documento_cm = "{$path}mun-e{$election_jus}-cm.json";
    $json_cm = json_decode(file_get_contents($documento_cm));
    foreach ($json_cm->abr as $uf) {
        $uf_muns[strtolower($uf->cd)] = array();
        foreach ($uf->mu as $mun) {
            array_push($uf_muns[strtolower($uf->cd)], $mun->cd);
        }
    }
    ksort($uf_muns);
}

// Começo das funções para pegar os partidos
function getPartidos($uf, $mun, $cargo)
{
    global $base_files;
    $path_aws = "https://www.correiobraziliense.com.br/_conteudo/apuracao2020/files/";
    $file_to_read = "{$path_aws}{$uf}-{$mun}-{$cargo}-partidos.json";
    $file_headers = @get_headers($file_to_read);
    if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
        // if (file_exists($file_to_read)) {
        return json_decode(file_get_contents($file_to_read));
    } else {
        return array();
    }
}

function setPartidos($uf, $mun, $cargo, $partidos)
{
    global $base_files;
    $file_to_write = "{$base_files}{$uf}-{$mun}-{$cargo}-partidos.json";
    file_put_contents($file_to_write, json_encode($partidos));
}

function updatePartidos($uf, $mun, $cargo, $json_var, $json_fixo)
{
    $partidos = (array) getPartidos($uf, $mun, $cargo);
    $coligacoes = $json_fixo->carg->col;
    foreach ($coligacoes as $col) {
        $col_partidos = $col->par;
        foreach ($col_partidos as $par) {
            if (!array_key_exists($par->n, $partidos)) {
                $partidos[$par->n] = array(
                    "numero" => $par->n,
                    "sigla" => $par->sg,
                    "nome" => $par->nm
                );
            }
        }
    }
    setPartidos($uf, $mun, $cargo, $partidos);
}
// Fim das funções para pegar os partidos

// Começo das funções para pegar os candidatos
function getCandidatos($uf, $mun, $cargo)
{
    global $base_files;
    $path_aws = "https://www.correiobraziliense.com.br/_conteudo/apuracao2020/files/";
    $file_to_read = "{$path_aws}{$uf}-{$mun}-{$cargo}-candidatos.json";
    $file_headers = @get_headers($file_to_read);
    if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
        // if (file_exists($file_to_read)) {
        return json_decode(file_get_contents($file_to_read));
    } else {
        return array();
    }
}

function setCandidatos($uf, $mun, $cargo, $candidatos)
{
    global $base_files;
    $file_to_write = "{$base_files}{$uf}-{$mun}-{$cargo}-candidatos.json";
    file_put_contents($file_to_write, json_encode($candidatos));
}

function updateCandidatos($uf, $mun, $cargo, $json_var, $json_fixo)
{
    $candidatos = (array) getCandidatos($uf, $mun, $cargo);
    $coligacoes = $json_fixo->carg->col;
    foreach ($coligacoes as $col) {
        $col_partidos = $col->par;
        foreach ($col_partidos as $par) {
            $part_cand = $par->cand;
            foreach ($part_cand as $cands) {
                if (!array_key_exists($cands->n, $candidatos)) {
                    $candidatos[$cands->n] = array(
                        "numero" => $cands->n,
                        "nome" => $cands->nmu,
                        "cod_foto" => $cands->sqcand,
                    );
                }
            }
        }
    }
    setCandidatos($uf, $mun, $cargo, $candidatos);
}
// Fim das funções para pegar os candidatos

// Função para pegar a imagem do candidato
function getImageCandidate($uf, $sqcand)
{
    global $env, $cicle, $election, $base_fotos, $host;
    $image_name = "{$sqcand}.jpeg";
    $dir_image = "{$base_fotos}{$uf}-{$image_name}";
    // if (!file_exists($dir_image)) {
    $image_url = "{$host}/{$cicle}/divulgacao/{$env}/{$election}/fotos/{$uf}/{$sqcand}.jpeg";
    //echo ($image_url . "<br>");
    file_put_contents($dir_image, file_get_contents($image_url));
    // }
}

// Começo das funções para pegar a apuração por município
function getApuracaoMunicipios($uf)
{
    global $base_files;
    $path_aws = "https://www.correiobraziliense.com.br/_conteudo/apuracao2020/files/";
    $file_to_read = "{$path_aws}{$uf}-municipios.json";
    $file_headers = @get_headers($file_to_read);
    if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
        return json_decode(file_get_contents($file_to_read));
    } else {
        return array();
    }
}

function setApuracaoMunicipio($uf, $municipios)
{
    global $base_files;
    $file_to_write = "{$base_files}{$uf}-municipios.json";
    file_put_contents($file_to_write, json_encode($municipios));
}

function updateApuracaoMunicipios($uf, $mun, $json_var, $json_fixo)
{
    // $json_municipios = (array) getApuracaoMunicipios($uf);
    $municipio_result = array();
    // if ($json_municipios) {
    //     $municipio_result = $json_municipios;
    //     if (in_array($mun, array_keys($municipio_result))) {
    //         $municipio_result[$mun]->arquivo_fixo = $json_var->nadf;
    //         $municipio_result[$mun]->total_apuracao = $json_var->abr[0]->tv;
    //         $municipio_result[$mun]->percentual_apuracao = trim($json_var->abr[0]->pst);
    //         $municipio_result[$mun]->total_votos_brancos = $json_var->abr[0]->vb;
    //         $municipio_result[$mun]->total_votos_nulos = $json_var->abr[0]->tvn;
    //         $municipio_result[$mun]->total_votos_validos = $json_var->abr[0]->vv;
    //         $municipio_result[$mun]->comparecimento = $json_var->abr[0]->c;
    //         $municipio_result[$mun]->abstencao = $json_var->abr[0]->a;
    //         echo '<pre>';
    //         print_r($municipio_result);
    //         echo '</pre>';
    //     } else {

    //         $municipio_result[$mun] = array(
    //             "arquivo_fixo" => $json_var->nadf,
    //             "cidade" => $json_fixo->nmabr,
    //             "cod_municipio" => $mun,
    //             "total_apuracao" => $json_var->abr[0]->tv,
    //             "percentual_apuracao" => trim($json_var->abr[0]->pst),
    //             "total_votos_brancos" => $json_var->abr[0]->vb,
    //             "total_votos_nulos" => $json_var->abr[0]->tvn,
    //             "total_votos_validos" => $json_var->abr[0]->vv,
    //             "comparecimento" => $json_var->abr[0]->c,
    //             "abstencao" => $json_var->abr[0]->a,
    //         );
    //     }
    //     // foreach ($json_municipios as $cod_mun => $json_mun) {
    //     //     if 
    //     // if ($json_var->nadf != $json_mun->arquivo_fixo) {
    //     //     $municipio_result[] = array(
    //     //         "arquivo_fixo" => $json_var->nadf,
    //     //         "cidade" => $json_fixo->nmabr,
    //     //         "cod_municipio" => $mun,
    //     //         "total_apuracao" => $json_var->abr[0]->tv,
    //     //         "percentual_apuracao" => trim($json_var->abr[0]->pst),
    //     //         "total_votos_brancos" => $json_var->abr[0]->vb,
    //     //         "total_votos_nulos" => $json_var->abr[0]->tvn,
    //     //         "total_votos_validos" => $json_var->abr[0]->vv,
    //     //         "comparecimento" => $json_var->abr[0]->c,
    //     //         "abstencao" => $json_var->abr[0]->a,
    //     //     );
    //     // }

    //     //     if ($mun != $json_mun->cod_municipio) {
    //     //         $municipio_result[] = array(
    //     //             "arquivo_fixo" => $json_var->nadf,
    //     //             "cidade" => $json_fixo->nmabr,
    //     //             "cod_municipio" => $mun,
    //     //             "total_apuracao" => $json_var->abr[0]->tv,
    //     //             "percentual_apuracao" => trim($json_var->abr[0]->pst),
    //     //             "total_votos_brancos" => $json_var->abr[0]->vb,
    //     //             "total_votos_nulos" => $json_var->abr[0]->tvn,
    //     //             "total_votos_validos" => $json_var->abr[0]->vv,
    //     //             "comparecimento" => $json_var->abr[0]->c,
    //     //             "abstencao" => $json_var->abr[0]->a,
    //     //         );
    //     //     }
    //     // }
    //     // setApuracaoMunicipio($uf, $municipio_result);
    // } else {
    return array(
        "arquivo_fixo" => $json_var->nadf,
        "cidade" => $json_fixo->nmabr,
        "cod_municipio" => $mun,
        "total_apuracao" => $json_var->abr[0]->tv,
        "percentual_apuracao" => $json_var->abr[0]->pst,
        "total_votos_brancos" => $json_var->abr[0]->vb,
        "total_votos_nulos" => $json_var->abr[0]->tvn,
        "total_votos_validos" => $json_var->abr[0]->vv,
        "comparecimento" => $json_var->abr[0]->c,
        "abstencao" => $json_var->abr[0]->a,
    );
    // setApuracaoMunicipio($uf, $municipio_result);
    // }
    //setApuracaoMunicipio($uf, $municipio_result);
}
// Fim das funções para pegar a apuração por município

// Começo das funções para pegar a apuração dos votos
function setApuracao($uf, $mun, $cargo, $apuracao)
{
    global $base_files;
    $file_to_write = "{$base_files}{$uf}-{$mun}-{$cargo}-result.json";
    file_put_contents($file_to_write, json_encode($apuracao));
}

function updateApuracao($uf, $mun, $cargo, $json_var, $json_fixo)
{
    global $cargos;
    $candidatos = (array) getCandidatos($uf, $mun, $cargo);
    $partidos = (array) getPartidos($uf, $mun, $cargo);
    $apuracao = array(
        "resumo" => array(
            "total_vagas" => $json_fixo->carg->nv,
            "nome_cargo" => $cargos[$cargo],
        ),
        "candidatos" => array()
    );
    $apuracao['resumo']['total_vagas'] = $json_fixo->carg->nv;
    // foreach ($json_var->abr[0]->cand as $apur) {
    foreach ($json_var->cand as $apur) {

        // Começar executar só uma vez
        // getImageCandidate($uf, $candidatos[$apur->n]->cod_foto);
        // Fim executar só uma vez

        $cod_partido = substr($candidatos[strval($apur->n)]->numero, 0, 2);
        $apuracao["candidatos"][] = array(
            "nome" => $candidatos[strval($apur->n)]->nome,
            "numero" => $candidatos[strval($apur->n)]->numero,
            "foto" => "https://www.correiobraziliense.com.br/_conteudo/apuracao2020/fotos/{$uf}-{$candidatos[strval($apur->n)]->cod_foto}.jpeg",
            "sigla_partido" => $partidos[$cod_partido]->sigla,
            "partido" => $partidos[$cod_partido]->nome,
            "total_votos" => $apur->vap,
            "percent_votos" => $apur->pvap,
            "status" => $apur->st,
        );
    }
    usort($apuracao['candidatos'], function ($a, $b) {
        return $a['total_votos'] <= $b['total_votos'];
    });
    setApuracao($uf, $mun, $cargo, $apuracao);
}
// Fim das funções para pegar a apuração dos votos

foreach ($uf_muns as $uf => $array_municipios) {
    $array_mun = array();
    foreach ($array_municipios as $cod_municipio) {
        foreach ($cargos as $cod_cargo => $nome_cargo) {
            $path = "{$host}/{$cicle}/divulgacao/{$env}/{$election}/dados/";
            $documento_var = "{$path}{$uf}/{$uf}{$cod_municipio}-c{$cod_cargo}-e{$election_jus}-v.json";
            $json_var = json_decode(file_get_contents($documento_var));
            $documento_fixo = "{$path}{$uf}/{$json_var->nadf}.json";
            $json_fixo = json_decode(file_get_contents($documento_fixo));


            $path_res = "{$host}/{$cicle}/divulgacao/{$env}/{$election}/dados-simplificados/";
            $documento_res = "{$path_res}{$uf}/{$uf}{$cod_municipio}-c{$cod_cargo}-e{$election_jus}-r.json";
            $json_res = json_decode(file_get_contents($documento_res));

            // Começar executar só uma vez
            // updatePartidos($uf, $cod_municipio, $cod_cargo, $json_var, $json_fixo);
            // updateCandidatos($uf, $cod_municipio, $cod_cargo, $json_var, $json_fixo);
            // Fim executar só uma vez

            updateApuracao($uf, $cod_municipio, $cod_cargo, $json_res, $json_fixo);
        }
        $array_mun[$cod_municipio] = getLastResultTSE($uf, $cod_municipio, $json_var, $json_fixo, $json_res);
    }
    setApuracaoMunicipio($uf, $array_mun);
}
