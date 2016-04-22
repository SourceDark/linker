<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;

$cache = array();

function getAllWithExpr($expr) {

    $offset = 0;
    $count = 1000;
    $result = array();

    while (true) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://oxfordhk.azure-api.net/academic/v1.0/evaluate?expr=" . $expr . "&count=" . $count . "&offset=" . $offset . "&attributes=Ti%2CY%2CCC%2CAA.AuN%2CAA.AuId%2CId%2CC.CN%2CC.CId%2CJ.JId%2CRId%2CF.FN%2CF.FId%2CAA.AfId",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 1000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "ocp-apim-subscription-key: f7cc29509a8443c5b3a5e56b0e38b5a6",
                "postman-token: dfbcf3cb-76cc-b2d1-eeef-0323b542bdb3"
            ),
            CURLOPT_SSL_VERIFYHOST, 0,
            CURLOPT_SSL_VERIFYPEER, 0
        ));
        $response = json_decode (curl_exec($curl));
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            $result = array_merge($result, $response -> entities);
        }

        if (count($response -> entities) < $count) break;
        else {
            $offset += $count;
        }
    }
    return $result;
}

function getById($id) {
    $list = getAllWithExpr("Id=" . $id);
    return $list[0];
}

function isPaper($id) {
    $paper = getById($id);
    if ($paper == NULL || $paper-> AA == NULL) return false;
    return true;
}

Route::get('/', function () {
    $id1 = Input::get('id1');
    $id2 = Input::get('id2');

    if ($id1 == null) return "Parameter(id1) Not Exists";
    if ($id2 == null) return "Parameter(id2) Not Exists";

    $ans = array();


    if (isPaper($id1)) {
        if (isPaper($id2)) {
            $p1 = getById($id1);
            $p2 = getById($id2);
            $anti_RIds = getAllWithExpr("RId=" . $id2);
            // id1 -> id3
            foreach ($p1 -> RId as $id3) {
                $p3 = getById($id3);
                //var_dump($p3);
                // id1 -> id3 -> id4
                foreach ($p3 -> RId as $id4) {
                    // id1 -> id3 -> id4 -> id2
                    foreach ($anti_RIds as $anti_RId) {
                        if ($id4 == $anti_RId -> Id) {
                            array_push($ans, array("P-P-P-P", intval($id1), $id3, $id4, intval($id2)));
                        }
                    }
                }
                // id1 -> id3 -> AuId3
                foreach ($p3 -> AA as $AA3) {
                    // id1 -> id3 -> AuId3 -> id2
                    foreach ($p2 -> AA as $AA2) {
                        if ($AA3 -> AuId == $AA2 -> AuId) {
                            array_push($ans, array("P-P-A-P", intval($id1), $id3, $AA3 -> AuId, intval($id2)));
                        }
                    }
                }
                // id1 -> id3 -> FId3
                if (isset($p3 -> F) && isset($p2 -> F)) {
                    foreach ($p3 -> F as $F3) {
                        // id1 -> id3 -> Fid3 -> id2
                        foreach ($p2 -> F as $F2) {
                            if ($F3 -> FId == $F2 -> FId) {
                                array_push($ans, array("P-P-F-P", intval($id1), $id3, $F3 -> FId, intval($id2)));
                            }
                        }
                    }
                }
                // id1 -> id3 -> CId3 -> id2
                if (isset($p3 -> C) && isset($p2 -> C)) {
                    if ($p3 -> C -> CId == $p2 -> C -> CId) {
                        array_push($ans, array("P-P-C-P", intval($id1), $id3, $p3 -> C -> CId, intval($id2)));
                    }
                }
                // id1 -> id3 -> JId3 -> id2
                if (isset($p3 -> J) && isset($p2 -> J)) {
                    if ($p3 -> J -> JId == $p2 -> J -> JId) {
                        array_push($ans, array("P-P-J-P", intval($id1), $id3, $p3 -> J -> JId, intval($id2)));
                    }
                }

            }
        }
    }

    return $ans;
});
