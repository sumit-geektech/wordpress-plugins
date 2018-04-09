<?php
require_once 'config.php';

global $db_connect;

if (isset($_GET['newcron1']))
    {
    $query = "Select * from coin_list_raw ";
    $result = mysqli_query($db_connect, $query);
    if (mysqli_num_rows($result) != 0)
        {
        while ($row = mysqli_fetch_assoc($result))
            {
            $coin_list_raw[] = $row;
            }
        }
      else
        {
        $coin_list_raw = array();
        }

    $querylist = "Select * from exchanges_list ";
    $resultlist = mysqli_query($db_connect, $querylist);
    $exchanges_name_lists = array();
    if (mysqli_num_rows($resultlist) != 0)
        {
        while ($rowlist = mysqli_fetch_assoc($resultlist))
            {

            if ($exchanges_name_lists)
                {
                $exchanges_name_lists[] = $rowlist['exchange_name'];
                }
              else
                {
                $exchanges_name_lists[] = $rowlist['exchange_name'];
                }
            }
        }

    foreach($coin_list_raw as $currency){ // foreach start 
        // Get Exchanges of the coin //
        $fsyms = $currency['coin_name'];
        $tsyms = $currency['currency'];
        $upper_fsyms = strtoupper($fsyms);
        $upper_tsyms = strtoupper($tsyms);
        //echo '<br/>';
        $querylist = "Select * from Average_calculate Where `coin_name` = '" . $fsyms . "' AND `to_currency` = '" . $tsyms . "'";
        $resultlist = mysqli_query($db_connect, $querylist);
        $roleslistnew = array();
        $e = '';
        if (mysqli_num_rows($resultlist) != 0){
            while ($rowlist = mysqli_fetch_assoc($resultlist)){
                $exchanges_name = unserialize($rowlist['exchanges_list']);
                if ($exchanges_name){
                    $e = array();
                    foreach($exchanges_name as $key => $exchange_name){
                        if ($e){
                            $e[] = $exchange_name;
                        } else {
                            $e[] = $exchange_name;
                        }
                    }
                }
            }
        }

        if ($e){
            $exchanges_request = $e;
        } else {
            $exchanges_request = $exchanges_name_lists;
        }

        // If Exchanges unselected delete its price

        if (!empty($e)){
            $query = "Select * from coins_list where coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
            $resulty = mysqli_query($db_connect, $query);

            // $coinlist = mysqli_fetch_assoc($resulty);

            while ($coinlist = mysqli_fetch_assoc($resulty)){
                if (!in_array($coinlist['exchange_name'], $e)){
                    $deleteExchange = "delete FROM coins_list WHERE exchange_name = '" . $coinlist['exchange_name'] . "'";
                    $deleteSuccess = mysqli_query($db_connect, $deleteExchange);
                }
            }
        }

        // If not selected exchanges then should check all the exchanges //
        // Get value from API with selected Exchanges.

       foreach($exchanges_request as $exchanges_requests){
            $exchanges_request = $exchanges_requests;

            // Get the api url

            $request_url = "https://min-api.cryptocompare.com/data/pricemultifull?fsyms=$fsyms&tsyms=$tsyms&e=$exchanges_request";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $request_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $parsed_json = curl_exec($ch);
            $parsed_json = json_decode($parsed_json);


            $apiData = $parsed_json;

            // API data check success or Error.

            if (!empty($apiData->RAW))
            {
                $market_supply = $apiData->RAW->$upper_fsyms->$upper_tsyms->SUPPLY;
                $exchange_name = $apiData->RAW->$upper_fsyms->$upper_tsyms->MARKET;
                $fsyms = $apiData->RAW->$upper_fsyms->$upper_tsyms->FROMSYMBOL;
                $tsyms = $apiData->RAW->$upper_fsyms->$upper_tsyms->TOSYMBOL;
                $price = $apiData->RAW->$upper_fsyms->$upper_tsyms->PRICE;

                //check if price exits exponent Value.
                $haystack = $price;
                $needle = 'e';
                $needle1 = 'E';
                if (strpos($haystack, $needle) !== false || strpos($haystack, $needle1) !== false){
                    $price = exp($price);
                    $price = str_replace('1.', '0.', $price);
                } else {
                    $price = $price;
                }  

                // checking coin exit in database or not and saving value in database table name coins_list.

                $query = "Select * from coins_list where exchange_name = '" . $exchange_name . "' AND coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
                $result = mysqli_query($db_connect, $query);
                if ($result->num_rows > 0){
                    $query = "UPDATE `coins_list` SET `currency_price`= '" . $price . "' WHERE exchange_name = '" . $exchange_name . "' AND coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
                    $resultUpdate = mysqli_query($db_connect, $query);
                } else {
                    $query = "INSERT INTO coins_list (coin_name,currency_price,exchange_name,FROMSYMBOL,TOSYMBOL )VALUES ('" . $fsyms . "','" . $price . "','" . $exchange_name . "','" . $fsyms . "','" . $tsyms . "')";
                    $result = mysqli_query($db_connect, $query);
                }

            } elseif ($apiData->Response == 'Error'){
                mailalert3($fsyms, $tsyms);
            }
        }
  

    // All coins average Calculate

    $query = "Select * from coins_list where coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
    $resulty = mysqli_query($db_connect, $query);
    if ($fsyms == 'BTC' && $tsyms == 'USD'){
                if ($resulty->num_rows >= 4){
                    $query45 = "Select (sum(currency_price) - max(currency_price) - min(currency_price)) / (count(*) - 2) as price_avg1 from coins_list where coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
                    $result45 = mysqli_query($db_connect, $query45);
                    $row45 = mysqli_fetch_assoc($result45);
                    $AVG1 = $row45;
                    $avg_price1 = $AVG1['price_avg1'];
                    } else {
                    $query = "Select AVG(currency_price) as price_avg from coins_list where coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
                    $result = mysqli_query($db_connect, $query);
                    $row = mysqli_fetch_assoc($result);
                    $AVG = $row;
                    $avg_price1 = $AVG['price_avg'];
                    }
                } elseif ($tsyms == 'BTC'){
                $query_btcusd = "Select * from coins_list where coin_name = 'BTC' AND TOSYMBOL = 'USD' ";
                $resultBtcUsd = mysqli_query($db_connect, $query_btcusd);
                $avg_priceBtc_usd = 1;
                if ($resultBtcUsd->num_rows >= 4){
                    $queryBtcUsd = "Select (sum(currency_price) - max(currency_price) - min(currency_price)) / (count(*) - 2) as price_avg1 from coins_list where coin_name = 'BTC' AND TOSYMBOL = 'USD' ";
                    $resultBtcUsd = mysqli_query($db_connect, $queryBtcUsd);
                    $rowBtcUsd = mysqli_fetch_assoc($resultBtcUsd);
                    $AVG1 = $rowBtcUsd;
                    $avg_priceBtc_usd = $AVG1['price_avg1'];
                    } else {
                    $queryBtcUsd = "Select AVG(currency_price) as price_avg from coins_list where coin_name = 'BTC' AND TOSYMBOL = 'USD' ";
                    $resultBtcUsd = mysqli_query($db_connect, $queryBtcUsd);
                    $rowBtcUsd = mysqli_fetch_assoc($resultBtcUsd);
                    $AVG1 = $rowBtcUsd;
                    $avg_priceBtc_usd = $AVG1['price_avg'];
                    }

                if ($resulty->num_rows >= 4){
                    $query45 = "Select (sum(currency_price) - max(currency_price) - min(currency_price)) / (count(*) - 2) as price_avg1 from coins_list where coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
                    $result45 = mysqli_query($db_connect, $query45);
                    $row45 = mysqli_fetch_assoc($result45);
                    $AVG1 = $row45;
                    $avg_price1 = $AVG1['price_avg1'] * $avg_priceBtc_usd;
                    } else {
                    $query = "Select AVG(currency_price) as price_avg from coins_list where coin_name = '" . $fsyms . "' AND TOSYMBOL = '" . $tsyms . "' ";
                    $result = mysqli_query($db_connect, $query);
                    $row = mysqli_fetch_assoc($result);
                    $AVG = $row;
                    $avg_price1 = $AVG['price_avg'] * $avg_priceBtc_usd;
                    }
                }


            $haystack = $avg_price1 . $tsyms;
            $needle = 'e';
            $needle1 = 'E';
            if (strpos($haystack, $needle) !== false || strpos($haystack, $needle1) !== false){
                $avg_price = exp($avg_price1);
                $avg_price = str_replace('1.', '0.', $avg_price);
                } else {
                $avg_price = (float)$avg_price1;
                }
            // Insert Into Average cron table    
            $query = "INSERT INTO Average_calculate_cron (coin_name,average,to_currency)
                    VALUES ('" . $fsyms . "','" . $avg_price . "','" . $tsyms . "')";
            $result = mysqli_query($db_connect, $query);
            $market_cap = $avg_price * $market_supply;

            // Get average price by time
            // Average result by hour    
     		$time = strtotime('-65 minutes');
            $time1 = date("Y/m/d H:i:s", $time);
            $time = strtotime('-55 minutes');
            $time = date("Y/m/d H:i:s", $time);
            $queryav = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = '" . $tsyms . "' ORDER BY id DESC LIMIT 1";
            $resultav = mysqli_query($db_connect, $queryav);
            $rowav = mysqli_fetch_assoc($resultav);

            // BTC to USD one hour before //

            $queryav1 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = 'BTC' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
            $resultav1 = mysqli_query($db_connect, $queryav1);
            $rowav1 = mysqli_fetch_assoc($resultav1);

            // ALT to BTC one one hour before //

            if ($fsyms == 'BTC'){
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            } else {
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            }

            // BTC to USD Right now//

            $queryav10 = "Select * from Average_calculate_cron where coin_name = 'BTC' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
            $resultav10 = mysqli_query($db_connect, $queryav10);
            $rowav10 = mysqli_fetch_assoc($resultav10);

            // ALT to BTc right now/

            if ($fsyms == 'BTC'){
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            } else {
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            }

            if (!empty($rowav['average'])) {
                $ourhourUSD = ($rowav20['average']);
                $ourhourBTC = ($rowav2['average']);
                $oneHour = round((($ourhourUSD - $ourhourBTC) / $ourhourBTC) * 100, 2);
            } else {
                $oneHour = 'N/A';
            }
            // Average result by 24 hour
            $time = strtotime('-14060 minutes');
            $time1 = date("Y/m/d H:i:s", $time);
            $time = strtotime('-1420 minutes');
            $time = date("Y/m/d H:i:s", $time);
            $queryav = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = '" . $tsyms . "' ORDER BY id DESC LIMIT 1";
            $resultav = mysqli_query($db_connect, $queryav);
            $rowav = mysqli_fetch_assoc($resultav);
            // BTC to USD 24 hour before //
            $queryav1 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = 'BTC' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
            $resultav1 = mysqli_query($db_connect, $queryav1);
            $rowav1 = mysqli_fetch_assoc($resultav1);
            // ALT to BTc one 24 hour before //
            if ($fsyms == 'BTC'){
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            } else {
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            }
            // BTC to USD Right now//
            $queryav10 = "Select * from Average_calculate_cron where coin_name = 'BTC' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
            $resultav10 = mysqli_query($db_connect, $queryav10);
            $rowav10 = mysqli_fetch_assoc($resultav10);
            // ALT to BTc right now/
            if ($fsyms == 'BTC'){
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            } else {
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            }
            if (!empty($rowav['average'])){
                // actual value
                $ourhourUSD = ($rowav20['average']);
                $ourhourBTC = ($rowav2['average']);
                $twentyfourHour = round((($ourhourUSD - $ourhourBTC) / $ourhourBTC) * 100, 2);
            } else {
                $twentyfourHour = 'N/A';
            }
             // Average result by 7 Days
            $time = strtotime('-192 hours');
            $time1 = date("Y/m/d H:i:s", $time);
            $time = strtotime('-168 hours');
            $time = date("Y/m/d H:i:s", $time);
            $queryav = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = '" . $tsyms . "' ORDER BY id DESC LIMIT 1";
            $resultav = mysqli_query($db_connect, $queryav);
            $rowav = mysqli_fetch_assoc($resultav);

            // ALT to BTC one 7d before //

            if ($fsyms == 'BTC'){
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            } else {
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            }

            // BTC to USD Right now//

            $queryav10 = "Select * from Average_calculate_cron where coin_name = 'BTC' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
            $resultav10 = mysqli_query($db_connect, $queryav10);
            $rowav10 = mysqli_fetch_assoc($resultav10);

            // ALT to BTC right now/

            if ($fsyms == 'BTC'){
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            } else {
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            }

            if (!empty($rowav['average'])){
                $ourhourUSD = ($rowav20['average']);
                $ourhourBTC = ($rowav2['average']);
                $sevenDays = round((($ourhourUSD - $ourhourBTC) / $ourhourBTC) * 100, 2);
            } else {
                $sevenDays = 'N/A';
            }
            // Average result by 30 Days
            $time = strtotime('-744 hours');
            $time1 = date("Y/m/d H:i:s", $time);
            $time = strtotime('-720 hours');
            $time = date("Y/m/d H:i:s", $time);
            $queryav = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = '" . $tsyms . "' ORDER BY id DESC LIMIT 1";
            $resultav = mysqli_query($db_connect, $queryav);
            $rowav = mysqli_fetch_assoc($resultav);

            // ALT to BTC one 30d before //

            if ($fsyms == 'BTC'){
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            } else {
                $queryav2 = "Select * from Average_calculate_cron where created_at BETWEEN '" . $time1 . "' AND '" . $time . "' AND coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav2 = mysqli_query($db_connect, $queryav2);
                $rowav2 = mysqli_fetch_assoc($resultav2);
            }

            // BTC to USD Right now//
            $queryav10 = "Select * from Average_calculate_cron where coin_name = 'BTC' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
            $resultav10 = mysqli_query($db_connect, $queryav10);
            $rowav10 = mysqli_fetch_assoc($resultav10);
            // ALT to BTC right now/
           if ($fsyms == 'BTC'){
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'USD' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            } else {
                $queryav20 = "Select * from Average_calculate_cron where coin_name = '" . $fsyms . "' AND to_currency = 'BTC' ORDER BY id DESC LIMIT 1";
                $resultav20 = mysqli_query($db_connect, $queryav20);
                $rowav20 = mysqli_fetch_assoc($resultav20);
            }
            if (!empty($rowav['average'])){
                $ourhourUSD = ($rowav20['average']);
                $ourhourBTC = ($rowav2['average']);
                $thirtyDays = round((($ourhourUSD - $ourhourBTC) / $ourhourBTC) * 100, 2);
            } else {
                $thirtyDays = 'N/A';
            }
            $query = "Select * from Average_calculate where coin_name = '" . $fsyms . "' AND to_currency = '" . $tsyms . "' ";
            $result = mysqli_query($db_connect, $query);
            if ($result->num_rows > 0){
                $query = "UPDATE `Average_calculate` SET `average`= '" . $avg_price . "',`total_coins_mined`= '" . $market_supply. "',`market_cap`= '" . $market_cap . "',`one_hour`= '" . $oneHour . "',`24_hours`= '" . $twentyfourHour . "',`7_day`= '" . $sevenDays . "',`30_days`= '" . $thirtyDays . "' WHERE coin_name = '" . $fsyms . "' AND to_currency = '" . $tsyms . "' ";
                $resultUpdate = mysqli_query($db_connect, $query);
            } else {
                $query = "INSERT INTO Average_calculate (coin_name,average,to_currency,total_coins_mined,market_cap,one_hour,24_hours,7_day,30_days)
                    VALUES ('" . $fsyms . "','" . $avg_price . "','" . $tsyms . "','" . $market_supply. "','" . $market_cap . "','" . $oneHour . "','" . $twentyfourHour . "','" . $sevenDays . "','" . $thirtyDays . "')";
                $result = mysqli_query($db_connect, $query);
            } 
	
           echo 'Average Calculate Successfully<br/>';

           //mail('gurjeet.geektech@gmail.com', 'Alert About Coin missing', 'hiiiiiiiiii');




  } // foreach close
    // Nok price for header calculations

    // Nok20 price calculation for 20 exchanges    
    $nok20_list = "Select * from nok20_list";
    $nok20List = mysqli_query($db_connect, $nok20_list);
    if ($nok20List->num_rows > 0) {
        while ($row = mysqli_fetch_assoc($nok20List)){
            $nok20Listing[] = $row;
        }
    } else $nok20Listing[] = array();
    foreach($nok20Listing as $currency) {
        if ($currency['currency'] == 'BTC') {
            $query2 = "Select * from Average_calculate where coin_name ='" . $currency['currency'] . "' AND to_currency = 'USD'";
        } else {
            $query2 = "Select * from Average_calculate where coin_name ='" . $currency['currency'] . "' AND to_currency = 'BTC'";
        }
        $result2 = mysqli_query($db_connect, $query2);
        $row2 = mysqli_fetch_assoc($result2);
        $query = "UPDATE `nok20_list` SET `currency_mkt`= '" . $row2['market_cap'] . "' WHERE currency = '" . $currency['currency'] . "' ";
        $resultUpdate = mysqli_query($db_connect, $query);
    }

    $query4 = "Select SUM(currency_mkt) as currency_mkt from nok20_list ";
    $result4 = mysqli_query($db_connect, $query4);
    $row4 = mysqli_fetch_assoc($result4);
    $AVG4 = $row4;
    $query = "INSERT INTO nok20_price(average_price)
                    VALUES ('" . $AVG4['currency_mkt'] . "' )";
    $result = mysqli_query($db_connect, $query);

    // Nok5 price calculation for 5 exchanges   
    $nok5_list = "Select * from nok5_list";
    $nok5List = mysqli_query($db_connect, $nok5_list);
    if ($nok5List->num_rows > 0){
        while ($row = mysqli_fetch_assoc($nok5List)){
            $nok5Listing[] = $row;
        }
    } else $nok5Listing[] = array();
    foreach($nok5Listing as $currency) {
        if ($currency['currency'] == 'BTC'){
            $query2 = "Select * from Average_calculate where coin_name ='" . $currency['currency'] . "' AND to_currency = 'USD'";
        } else {
            $query2 = "Select * from Average_calculate where coin_name ='" . $currency['currency'] . "' AND to_currency = 'BTC'";
        }
        $result2 = mysqli_query($db_connect, $query2);
        $row2 = mysqli_fetch_assoc($result2);
        $query = "UPDATE `nok5_list` SET `currency_mkt`= '" . $row2['market_cap'] . "' WHERE currency = '" . $currency['currency'] . "' ";
        $resultUpdate = mysqli_query($db_connect, $query);
    }

    $query4 = "Select SUM(currency_mkt) as currency_mkt from nok5_list ";
    $result4 = mysqli_query($db_connect, $query4);
    $row4 = mysqli_fetch_assoc($result4);
    $AVG4 = $row4;
    $query = "INSERT INTO nok5_price(average_price)
                        VALUES ('" . $AVG4['currency_mkt'] . "')";
    $result = mysqli_query($db_connect, $query);
    // Delete 30 days past record

    $queryav = "delete FROM Average_calculate_cron WHERE created_at < NOW() - INTERVAL 33 DAY";
    $resultav = mysqli_query($db_connect, $queryav);
    
    // mail if API cannot get Values
    function mailalert3($from, $tocoin){
        global $db_connect;
        $query2 = "Select * from Average_calculate where coin_name ='" . $from . "' AND to_currency = '" . $tocoin . "'";
        $result2 = mysqli_query($db_connect, $query2);
        $row2 = mysqli_fetch_assoc($result2);


        if ($result2->num_rows > 0){
            $time = time();
            $past = $time - 7200;
            if ($row2['missing_alert'] < $past || $row2['missing_alert'] == ''){
                $query = "UPDATE `Average_calculate` SET `missing_alert`= '" . $time . "' WHERE coin_name = '" . $from . "' AND to_currency = '" . $tocoin . "' ";


                $email = get_option('admin_email');
                $message = "Hello Admin,The API is unable to get data for $from to $tocoin.Thanks ";
                mail($email, 'Alert About Coin missing', $message);
                }
            } else {
            $time = time();
            $query = "INSERT INTO Average_calculate (coin_name,to_currency,missing_alert)
                    VALUES ('" . $from . "','" . $tocoin . "','" . $time . "')";

            // $result = mysqli_query($db_connect, $query);

            $email = get_option('admin_email');
            $message = "Hello Admin,The API is unable to get data for $from to $tocoin.Thanks ";
            //mail($email, 'Alert About Coin missing', $message);
            }
    }
    echo 'Completed';
    exit;
    }