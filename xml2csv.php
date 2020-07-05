<?php

/**
 * XML extract from brazilian fiscal invoice (NFe) and CSV output
 * @version 1.0
 * @author Marcos Bezerra [mbezerra@gmail.com]
 * @license This code is licensed under the MIT License:
 * 
 * Copyright 2020 Marcos Bezerra
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

$sourceDirName = "<SOURCE DIR XML FILES AND OUTPUT NAME>";
$directory = $sourceDirName . "/*";
$header = ['versao', 'ns1:serie', 'ns1:nNF', 'ns1:dEmi', 'nItem', 'ns1:cProd', 'ns1:CFOP', 'ns1:vProd', 'ns1:vDesc', 'ns1:vICMS', 'ns1:vIPI', 'ns1:vPIS', 'ns1:vCOFINS', 'ns1:vNF', 'ns1:chNFe'];

$out = fopen($sourceDirName . '.csv', 'w+');
fputcsv($out, $header, ';');

foreach(glob($directory) as $xml) {
    if(filesize($xml) != 0) {
        $xmlExtracted = simplexml_load_file($xml);   
        $xmlArray = xml2array($xmlExtracted);

        $vPISt = floatval($xmlArray['NFe']['infNFe']['total']['ICMSTot']['vPIS']);
        $vCOFINSt = floatval($xmlArray['NFe']['infNFe']['total']['ICMSTot']['vCOFINS']);
        $vIPIt = floatval($xmlArray['NFe']['infNFe']['total']['ICMSTot']['vIPI']);

        $data = [
            'xmlArray' => $xmlArray,
            'vPISt' => $vPISt,
            'vCOFINSt' => $vCOFINSt,
            'vIPIt' => $vIPIt,
            'key' => 0,
            'prodArray' => null,
            'qtdItens' => 1
        ];

        if(array_key_exists(0, $xmlArray['NFe']['infNFe']['det'])) {
            $data['qtdItens'] = count($xmlArray['NFe']['infNFe']['det']);
            $det = xml2array($xmlArray['NFe']['infNFe']['det']);

            foreach ($det as $key => $prodArray) {
                $data['key'] = $key;
                $data['prodArray'] = $prodArray;
                $row = mountOutputToCsv($data);
                fputcsv($out, $row, ';', '"');    
            }
        } else {
            $row = mountOutputToCsv($data);
            fputcsv($out, $row, ';', '"');
        }
    } else {
        var_dump($xml);
        continue;
    }
}

fclose($out);

/**
 * xml2array
 *
 * @param  object $xmlObject
 * @param  array $out
 * @return array $out
 */
function xml2array ($xmlObject, $out = []) {
    foreach ( (array) $xmlObject as $index => $node )
        $out[$index] = ( is_object ( $node ) ) ? xml2array ( $node ) : $node;

    return $out;
}

/**
 * mountOutputToCsv
 *
 * @param  array data
 * @return array $row
 */
function mountOutputToCsv($data) {    
    $xmlArray = $data['xmlArray'];
    $vPISt = $data['vPISt'];
    $vCOFINSt = $data['vCOFINSt'];
    $vIPIt = $data['vIPIt'];
    $key = $data['key'];
    $prodArray = $data['prodArray'];
    $qtdItens = $data['qtdItens'];

    $row[0] = $xmlArray['NFe']['infNFe']['@attributes']['versao'];

    $row[1] = $xmlArray['NFe']['infNFe']['ide']['serie'];

    $row[2] = strval('"' . $xmlArray['NFe']['infNFe']['ide']['nNF'] . '"');

    if(array_key_exists('dEmi', $xmlArray['NFe']['infNFe']['ide'])) {
        $row[3] = date('d/m/Y', strtotime($xmlArray['NFe']['infNFe']['ide']['dEmi']));
    } elseif(array_key_exists('dhEmi', $xmlArray['NFe']['infNFe']['ide'])) {
        $row[3] = date('d/m/Y', strtotime($xmlArray['NFe']['infNFe']['ide']['dhEmi']));
    }
    
    $row[4] = $prodArray ? $prodArray['@attributes']['nItem'] : $xmlArray['NFe']['infNFe']['det']['@attributes']['nItem'];

    $row[5] = $prodArray ? strval('"' . $prodArray['prod']['cProd'] . "'") : strval('"' . $xmlArray['NFe']['infNFe']['det']['prod']['cProd'] . '"');

    $row[6] = $prodArray ? $prodArray['prod']['CFOP'] : $xmlArray['NFe']['infNFe']['det']['prod']['CFOP'];

    $row[7] = $prodArray ? number_format($prodArray['prod']['vProd'], 2, ',', '.') : number_format($xmlArray['NFe']['infNFe']['det']['prod']['vProd'], 2, ',', '.');

    $descFrom = $prodArray ? $prodArray['prod'] : $xmlArray['NFe']['infNFe']['det']['prod'];
    if(array_key_exists('vDesc', $descFrom)) {
        $desc = floatval($descFrom['vDesc']);
    } else {
        $desc = 0.00;
    }
    $row[8] = number_format($desc, 2, ',', '.');

    $icmsFrom = $prodArray ? $prodArray['imposto']['ICMS'] : $xmlArray['NFe']['infNFe']['det']['imposto']['ICMS'];
    $firstKey = array_key_first($icmsFrom);
    if(!in_array($icmsFrom[$firstKey]['CST'], ['30', '40', '41', '50', '51', '60'])) {
        $vICMS = $icmsFrom[$firstKey]['vICMS'];
    } else {
        $vICMS = 0.00;
    }
    $row[9] = number_format($vICMS, 2, ',', '.');

    $impostoFrom = $prodArray ? $prodArray['imposto'] : $xmlArray['NFe']['infNFe']['det']['imposto'];
    if(array_key_exists('IPI', $impostoFrom)) {
        if(array_key_exists('IPITrib', $impostoFrom['IPI'])) {
            $vIPI = $impostoFrom['IPI']['IPITrib']['vIPI'];
        } else {
            $vIPI = 0.00;
        }
    } else {
        $vIPI = 0.00;
    }
    if(!floatval($vIPI) && floatval($vIPIt)) {
        $vIPI = $vIPIt / $qtdItens;
    }
    $row[10] = number_format($vIPI, 2, ',', '.');

    $pisFrom = $prodArray ? $prodArray['imposto']['PIS'] : $xmlArray['NFe']['infNFe']['det']['imposto']['PIS'];
    $firstKey = array_key_first($pisFrom);
    if(!in_array($pisFrom[$firstKey]['CST'], ['07','08','09'])) {
        if(array_key_exists('vPIS', $pisFrom[$firstKey])) {
            $vPIS = $pisFrom[$firstKey]['vPIS'];
        } else {
            $vPIS = 0.00;
        }
    } else {
        $vPIS = 0.00;
    }
    if(!floatval($vPIS) && floatval($vPISt)) {
        $vPIS = $vPISt / $qtdItens;
    }
    $row[11] = number_format($vPIS, 2, ',', '.');

    $cofinsFrom = $prodArray ? $prodArray['imposto']['COFINS'] : $xmlArray['NFe']['infNFe']['det']['imposto']['COFINS'];
    $firstKey = array_key_first($cofinsFrom);
    if(!in_array($cofinsFrom[$firstKey]['CST'], ['07','08','09'])) {
        if(array_key_exists('vCOFINS', $cofinsFrom[$firstKey])) {
            $vCOFINS = $cofinsFrom[$firstKey]['vCOFINS'];
        } else {
            $vCOFINS = 0.00;
        }
    } else {
        $vCOFINS = 0.00;
    }
    if(!floatval($vCOFINS) && floatval($vCOFINSt)) {
        $vCOFINS = $vCOFINSt / $qtdItens;
    }
    $row[12] = number_format($vCOFINS, 2, ',', '.');

    $row[13] = ($key + 1) == $qtdItens ? number_format($xmlArray['NFe']['infNFe']['total']['ICMSTot']['vNF'], 2, ',', '.') : '';

    $row[14] = ($key + 1) == $qtdItens ? strval('"' . $xmlArray['protNFe']['infProt']['chNFe'] . '"') : '';

    return $row;
}
function mountOutputToCsv($data) {    
    $xmlArray = $data['xmlArray'];
    $vPISt = $data['vPISt'];
    $vCOFINSt = $data['vCOFINSt'];
    $vIPIt = $data['vIPIt'];
    $key = $data['key'];
    $prodArray = $data['prodArray'];
    $qtdItens = $data['qtdItens'];
    $row[15] = '';

    $row[0] = $xmlArray['NFe']['infNFe']['@attributes']['versao'];

    $row[1] = $xmlArray['NFe']['infNFe']['ide']['serie'];

    $row[2] = strval('"' . $xmlArray['NFe']['infNFe']['ide']['nNF'] . '"');

    if(array_key_exists('dEmi', $xmlArray['NFe']['infNFe']['ide'])) {
        $row[3] = date('d/m/Y', strtotime($xmlArray['NFe']['infNFe']['ide']['dEmi']));
    } elseif(array_key_exists('dhEmi', $xmlArray['NFe']['infNFe']['ide'])) {
        $row[3] = date('d/m/Y', strtotime($xmlArray['NFe']['infNFe']['ide']['dhEmi']));
    }
    
    $row[4] = $prodArray ? $prodArray['@attributes']['nItem'] : $xmlArray['NFe']['infNFe']['det']['@attributes']['nItem'];

    $row[5] = $prodArray ? strval('"' . $prodArray['prod']['cProd'] . "'") : strval('"' . $xmlArray['NFe']['infNFe']['det']['prod']['cProd'] . '"');

    $row[6] = $prodArray ? $prodArray['prod']['CFOP'] : $xmlArray['NFe']['infNFe']['det']['prod']['CFOP'];

    $qCom = $prodArray ? floatval($prodArray['prod']['qCom']) : floatval($xmlArray['NFe']['infNFe']['det']['prod']['qCom']);
    $vProd = $prodArray ? $prodArray['prod']['vProd'] : $xmlArray['NFe']['infNFe']['det']['prod']['vProd'];
    $row[7] = number_format($vProd, 2, ',', '.');

    $descFrom = $prodArray ? $prodArray['prod'] : $xmlArray['NFe']['infNFe']['det']['prod'];
    if(array_key_exists('vDesc', $descFrom)) {
        $desc = floatval($descFrom['vDesc']);
    } else {
        $desc = 0.00;
    }
    $row[8] = number_format($desc, 2, ',', '.');

    $icmsFrom = $prodArray ? $prodArray['imposto']['ICMS'] : $xmlArray['NFe']['infNFe']['det']['imposto']['ICMS'];
    $firstKey = array_key_first($icmsFrom);
    if(!in_array($icmsFrom[$firstKey]['CST'], ['30', '40', '41', '50', '51', '60'])) {
        $vICMS = $icmsFrom[$firstKey]['vICMS'];
    } else {
        $vICMS = 0.00;
    }
    $row[9] = number_format($vICMS, 2, ',', '.');

    $impostoFrom = $prodArray ? $prodArray['imposto'] : $xmlArray['NFe']['infNFe']['det']['imposto'];
    if(array_key_exists('IPI', $impostoFrom)) {
        if(array_key_exists('IPITrib', $impostoFrom['IPI'])) {
            $pIPI = $impostoFrom['IPI']['IPITrib']['pIPI'];
            $vIPI = $impostoFrom['IPI']['IPITrib']['vIPI'];
        } else {
            $pIPI = 0.00;
            $vIPI = 0.00;
        }
    } else {
        $pIPI = 0.00;
        $vIPI = 0.00;
    }
    if(!floatval($vIPI) && !floatval($pIPI) && floatval($vIPIt)) {
        $vIPI = $vIPIt / $qtdItens;
        if($row[15] != '') {
            $row[15] .= ' | ';
        }
        $row[15] .= 'IPI calculado pela média';
    } elseif(!floatval($vIPI) && floatval($pIPI)) {
        $vIPI = $qCom * $vProd * $pIPI / 100;
    }
    $row[10] = number_format($vIPI, 2, ',', '.');

    $pisFrom = $prodArray ? $prodArray['imposto']['PIS'] : $xmlArray['NFe']['infNFe']['det']['imposto']['PIS'];
    $firstKey = array_key_first($pisFrom);
    if(!in_array($pisFrom[$firstKey]['CST'], ['07','08','09'])) {
        if(array_key_exists('vPIS', $pisFrom[$firstKey])) {
            $vPIS = $pisFrom[$firstKey]['vPIS'];
        } else {
            $vPIS = 0.00;
        }
        if(array_key_exists('pPIS', $pisFrom[$firstKey])) {
            $pPIS = $pisFrom[$firstKey]['pPIS'];
        } else {
            $pPIS = 0.00;
        }
    } else {
        $pPIS = 0.00;
        $vPIS = 0.00;
    }
    if(!floatval($vPIS) && !floatval($pPIS) && floatval($vPISt)) {
        $vPIS = $vPISt / $qtdItens;
        if($row[15] != '') {
            $row[15] .= ' | ';
        }
        $row[15] .= 'PIS calculado pela média';
    } elseif(!floatval($vPIS) && floatval($pPIS)) {
        $vPIS = $qCom * $vProd * $pPIS / 100;
    }
    $row[11] = number_format($vPIS, 2, ',', '.');

    $cofinsFrom = $prodArray ? $prodArray['imposto']['COFINS'] : $xmlArray['NFe']['infNFe']['det']['imposto']['COFINS'];
    $firstKey = array_key_first($cofinsFrom);
    if(!in_array($cofinsFrom[$firstKey]['CST'], ['07','08','09'])) {
        if(array_key_exists('vCOFINS', $cofinsFrom[$firstKey])) {
            $vCOFINS = $cofinsFrom[$firstKey]['vCOFINS'];
        } else {
            $vCOFINS = 0.00;
        }
        if(array_key_exists('pCOFINS', $cofinsFrom[$firstKey])) {
            $pCOFINS = $cofinsFrom[$firstKey]['pCOFINS'];
        } else {
            $pCOFINS = 0.00;
        }
    } else {
        $pCOFINS = 0.00;
        $vCOFINS = 0.00;
    }
    if(!floatval($vCOFINS) && !floatval($pCOFINS) && floatval($vCOFINSt)) {
        $vCOFINS = $vCOFINSt / $qtdItens;
        if($row[15] != '') {
            $row[15] .= ' | ';
        }
        $row[15] .= 'COFINS calculado pela média';
    } elseif(!floatval($vCOFINS) && floatval($pCOFINS)) {
        $vCOFINS = $qCom * $vProd * $pCOFINS / 100;
    }
    $row[12] = number_format($vCOFINS, 2, ',', '.');

    $row[13] = ($key + 1) == $qtdItens ? number_format($xmlArray['NFe']['infNFe']['total']['ICMSTot']['vNF'], 2, ',', '.') : '';

    $row[14] = ($key + 1) == $qtdItens ? strval('"' . $xmlArray['protNFe']['infProt']['chNFe'] . '"') : '';
    
    ksort($row);
    return $row;
}
