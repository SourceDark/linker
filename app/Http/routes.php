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
    if ($paper == NULL || isset($paper-> AA) == false) return false;
    return true;
}

Route::get('/', function () {
    $id1 = Input::get('id1');
    $id2 = Input::get('id2');

    if ($id1 == null) return "Parameter(id1) Not Exists";
    if ($id2 == null) return "Parameter(id2) Not Exists";

    $ans = array();


    if (isPaper($id1)) {
        $p1 = getById($id1);
        if (isPaper($id2)) {
            $p2 = getById($id2);
            $anti_RIds = getAllWithExpr("RId=" . $id2);
            // id1 -> id3
            foreach ($p1->RId as $id3) {
                if ($id3 == $id2) {
                    array_push($ans, array("P-P", intval($id1), intval($id2)));
                }
                $p3 = getById($id3);
                // id1 -> id3 -> id4
                foreach ($p3->RId as $id4) {
                    if ($id4 == $id2) {
                        array_push($ans, array("P-P-P", intval($id1), $id3, intval($id2)));
                    }
                    // id1 -> id3 -> id4 -> id2
                    foreach ($anti_RIds as $anti_RId) {
                        if ($id4 == $anti_RId->Id) {
                            array_push($ans, array("P-P-P-P", intval($id1), $id3, $id4, intval($id2)));
                        }
                    }
                }
                // id1 -> id3 -> AuId3
                foreach ($p3->AA as $AA3) {
                    // id1 -> id3 -> AuId3 -> id2
                    foreach ($p2->AA as $AA2) {
                        if ($AA3->AuId == $AA2->AuId) {
                            array_push($ans, array("P-P-A-P", intval($id1), $id3, $AA3->AuId, intval($id2)));
                        }
                    }
                }
                // id1 -> id3 -> FId3
                if (isset($p3->F) && isset($p2->F)) {
                    foreach ($p3->F as $F3) {
                        // id1 -> id3 -> Fid3 -> id2
                        foreach ($p2->F as $F2) {
                            if ($F3->FId == $F2->FId) {
                                array_push($ans, array("P-P-F-P", intval($id1), $id3, $F3->FId, intval($id2)));
                            }
                        }
                    }
                }
                // id1 -> id3 -> CId3 -> id2
                if (isset($p3->C) && isset($p2->C)) {
                    if ($p3->C->CId == $p2->C->CId) {
                        array_push($ans, array("P-P-C-P", intval($id1), $id3, $p3->C->CId, intval($id2)));
                    }
                }
                // id1 -> id3 -> JId3 -> id2
                if (isset($p3->J) && isset($p2->J)) {
                    if ($p3->J->JId == $p2->J->JId) {
                        array_push($ans, array("P-P-J-P", intval($id1), $id3, $p3->J->JId, intval($id2)));
                    }
                }

            }
            // id2 -> id4
            foreach ($anti_RIds as $p4) {
                // id1 <-> AuId1 <-> id4 -> id2
                foreach ($p1->AA as $AA1) {
                    foreach ($p4->AA as $AA4) {
                        if ($AA1->AuId == $AA4->AuId) {
                            array_push($ans, array("P-A-P-P", (float)$id1, $AA1->AuId, $p4->Id, (float)$id2));
                        }
                    }
                }
                // id1 <-> F <-> id4 -> id2
                if (isset($p1->F) && isset($p4->F)) {
                    foreach ($p1->F as $F1) {
                        foreach ($p4->F as $F4) {
                            if ($F1->FId == $F4->FId) {
                                array_push($ans, array("P-F-P-P", (float)$id1, $F1->FId, $p4->Id, (float)$id2));
                            }
                        }
                    }
                }
                // id1 <-> C <-> id4 -> id2
                if (isset($p1->C) && isset($p4->C)) {
                    if ($p1->C->CId == $p4->C->CId) {
                        array_push($ans, array("P-C-P-P", (float)$id1, $p1->C->CId, $p4->Id, (float)$id2));
                    }
                }
                // id1 <-> J <-> id4 -> id2
                if (isset($p1->J) && isset($p4->J)) {
                    if ($p1->J->JId == $p4->J->JId) {
                        array_push($ans, array("P-J-P-P", (float)$id1, $p1->J->JId, $p4->Id, (float)$id2));
                    }
                }
            }
            // id1 -> AuId2 <- id2
            foreach ($p1->AA as $AA1) {
                foreach ($p2->AA as $AA2) {
                    if ($AA1->AuId == $AA2->AuId) {
                        array_push($ans, array("P-A-P", (float)$id1, $AA1->AuId, (float)$id2));
                    }
                }
            }
            // id1 -> F <- id2
            if (isset($p1->F) && isset($p2->F)) {
                foreach ($p1->F as $F1) {
                    foreach ($p2->F as $F2) {
                        if ($F1->FId == $F2->FId) {
                            array_push($ans, array("P-F-P", (float)$id1, $F1->FId, (float)$id2));
                        }
                    }
                }
            }
            // id1 -> C <- id2
            if (isset($p1->C) && isset($p2->C)) {
                if ($p1->C->CId == $p2->C->CId) {
                    array_push($ans, array("P-C-P", (float)$id1, $p1->C->CId, (float)$id2));
                }
            }
            // id1 -> J <- id2
            if (isset($p1->J) && isset($p2->J)) {
                if ($p1->J->JId == $p2->J->JId) {
                    array_push($ans, array("P-J-P", (float)$id1, $p1->J->JId, (float)$id2));
                }
            }
        } else {
            $AuId2 = $id2;
            // id1 <-> AuId2
            foreach ($p1->AA as $AA1) {
                if ($AA1->AuId == $AuId2) {
                    array_push($ans, array("P-A", (float)$id1, (float)$AuId2));
                }
            }
            $p4s = getAllWithExpr("Composite(AA.AuId=" . $id2 . ")");
            // id4 <- AuId2
            foreach ($p4s as $p4) {
                $id4 = $p4->Id;
                // id1 -> id4 <- AuId2
                foreach ($p1->RId as $rid) {
                    if ($rid == $p4->Id) {
                        array_push($ans, array("P-P-A", (float)$id1, $p4->Id, (float)$AuId2));
                    }
                }
                // id1 -> id3 <- id4 <- AuId2
                $anti_RIds = getAllWithExpr("RId=" . $id4);
                foreach ($anti_RIds as $p3) {
                    foreach ($p1->RId as $rid) {
                        if ($rid == $p3->Id) {
                            array_push($ans, array("P-P-P-A", (float)$id1, $p3->Id, $p4->Id, (float)$AuId2));
                        }
                    }
                }
                // id1 -> AuId3 <- id4 <- AuId2
                foreach ($p1->AA as $AA1)
                    foreach ($p4->AA as $AA4)
                        if ($AA1->AuId == $AA4->AuId)
                            array_push($ans, array("P-A-P-A", (float)$id1, $AA1->AuId, $id4, (float)$AuId2));
                // Id1 -> FId3 <- Id4 <- AuId 2
                if (isset($p1->F) && isset($p4->F))
                    foreach ($p1->F as $F1)
                        foreach ($p4->F as $F4)
                            if ($F1->FId == $F4->FId)
                                array_push($ans, array("P-F-P-A", (float)$id1, $F1->FId, $id4, (float)$AuId2));
                // Id1 -> CId3 <- Id4 <- AuId 2
                if (isset($p1->C) && isset($p4->C))
                    if ($p1->C->CId == $p4->C->CId)
                        array_push($ans, array("P-C-P-A", (float)$id1, $p1->C->CId, $id4, (float)$AuId2));
                // Id1 -> JId3 <- Id4 <- AuId 2
                if (isset($p1->J) && isset($p4->J))
                    if ($p1->J->JId == $p4->J->JId)
                        array_push($ans, array("P-J-P-A", (float)$id1, $p1->J->JId, $id4, (float)$AuId2));
            }
            // PAFA
            $fids = array();
            foreach ($p4s as $p4)
                foreach ($p4 -> AA as $AA4) if (isset($AA4 -> AfId) && $AA4 -> AuId == $AuId2) {
                    if (!in_array($AA4 -> AfId, $fids)) {
                        array_push($fids, $AA4 -> AfId);
                    }
                }
            foreach ($p1 -> AA as $AA1) if (isset($AA1 -> AuId)) {
                $p3s = getAllWithExpr("Composite(AA.AuId=" . $AA1 -> AuId . ")");
                $ids = array();
                foreach ($p3s as $p3)
                    foreach ($p3 -> AA as $AA3) if (isset($AA3 -> AfId) && $AA3 -> AuId == $AA1 -> AuId)
                        if (!in_array($AA3 -> AfId, $ids)) {
                            array_push($ids, $AA3 -> AfId);
                            if (in_array($AA3 -> AfId, $fids)) {
                                array_push($ans, array("P-A-F-A", (float)$id1, $AA1 -> AuId, $AA3 -> AfId, (float)$AuId2));
                            }
                        }
            }
        }
    }
    else {
        $AuId1 = $id1;
        $p1s = getAllWithExpr("Composite(AA.AuId=" . $AuId1 . ")");
        if (isPaper($id2)) {
            $p2 = getById($id2);
            // AP
            foreach ($p2 -> AA as $AA1) {
                if ($AA1 -> AuId == $AuId1) {
                    array_push($ans, array("A-P", (float) $AuId1, (float) $id2));
                }
            }
            $p4s = getAllWithExpr("RId=" . $id2);
            // APP
            foreach ($p4s as $p4)
                foreach ($p4 -> AA as $AA4)
                    if ($AA4 -> AuId == $AuId1)
                        array_push($ans, array("A-P-P", (float) $AuId1, $p4 -> Id, (float) $id2));
            // APPP
            foreach ($p1s as $p1)
                foreach ($p1 -> RId as $rid)
                    foreach ($p4s as $p4)
                        if ($rid == $p4 -> Id)
                            array_push($ans, array("A-P-P-P", (float) $AuId1, $p1 -> Id, $p4 -> Id, (float) $id2));
            // APAP
            foreach ($p1s as $p1)
                foreach ($p1 -> AA as $AA1)
                    foreach ($p2 -> AA as $AA2)
                        if ($AA1 -> AuId == $AA2 -> AuId)
                            array_push($ans, array("A-P-A-P", (float) $AuId1, $p1 -> Id, $AA2 -> AuId, (float) $id2));
            // APFP
            if (isset($p2 -> F))
                foreach ($p1s as $p1) if (isset($p1 -> F))
                    foreach ($p1 -> F as $F1)
                        foreach ($p2 -> F as $F2)
                            if ($F1 -> FId == $F2 -> FId)
                                array_push($ans, array("A-P-A-P", (float) $AuId1, $p1 -> Id, $F2 -> FId, (float) $id2));
            // APCP
            if (isset($p2 -> C))
                foreach ($p1s as $p1) if (isset($p1 -> C)) if (isset($p1 -> C))
                    if ($p1 -> C -> CId == $p2 -> C -> CId)
                        array_push($ans, array("A-P-C-P", (float) $AuId1, $p1 -> Id, $p2 -> C -> CId, (float) $id2));
            // APJP
            if (isset($p2 -> J))
                foreach ($p1s as $p1) if (isset($p1 -> J)) if (isset($p1 -> J))
                    if ($p1 -> J -> JId == $p2 -> J -> JId)
                        array_push($ans, array("A-P-J-P", (float) $AuId1, $p1 -> Id, $p2 -> J -> JId, (float) $id2));
            // AFAP
            $ids = array();
            foreach ($p1s as $p1)
                foreach ($p1 -> AA as $AA1) if (isset($AA1 -> AfId) && $AA1 -> AuId == $id1)
                    if (!in_array($AA1 -> AfId, $ids)) {
                        array_push($ids, $AA1 -> AfId);
                        foreach ($p2 -> AA as $AA2) if (isset($AA2 -> AfId) && $AA2 -> AfId == $AA1 -> AfId)
                            array_push($ans, array("A-F-A-P", (float) $AuId1, $AA1 -> AfId, $AA2 -> AuId, (float) $id2));
                    }
        }
        else {
            $AuId2 = $id2;
            $p2s = getAllWithExpr("Composite(AA.AuId=" . $id2 . ")");
            // AIA
            $AfIds1 = array();
            $AfIds2 = array();
            foreach ($p1s as $p1)
                foreach ($p1 -> AA as $AA1) if ($AA1 -> AuId == $AuId1 && isset($AA1 -> AfId))
                    array_push($AfIds1, $AA1 -> AfId);
            foreach ($p2s as $p2)
                foreach ($p2 -> AA as $AA2) if ($AA2 -> AuId == $AuId2 && isset($AA2 -> AfId))
                    array_push($AfIds2, $AA2 -> AfId);
            $AfIds1 = array_unique($AfIds1);
            $AfIds2 = array_unique($AfIds2);
            foreach ($AfIds1 as $AfId1)
                foreach ($AfIds2 as $AfId2)
                    if ($AfId1 == $AfId2)
                        array_push($ans, array("A-I-A", (float) $AuId1, $AfId1, (float) $AuId2));
            // APA
            foreach ($p1s as $p1)
                foreach ($p2s as $p2)
                    if ($p1 -> Id == $p2 -> Id)
                        array_push($ans, array("A-P-A", (float) $AuId1, $p1 -> Id, (float) $AuId2));
            // APPA
            $ids = array();
            foreach ($p1s as $p1)
                foreach ($p1 -> RId as $rid)
                    foreach ($p2s as $p2)
                        if ($rid == $p2 -> Id)
                            array_push($ans, array("A-P-P-A", (float) $AuId1, $p1 -> Id, $p2 -> Id, (float) $AuId2));
        }
    }

    for ($i = 0; $i < count($ans); $i++) array_shift($ans[$i]);
    return $ans;
    return array(
        "count" => count($ans),
        "entities" => $ans
    );
});
