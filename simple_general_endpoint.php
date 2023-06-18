<?php

    include 'callAPI.php';
	include_once 'api.php';

    $baseUrl = getMarketplaceBaseUrl();

    //hardcoded stuff
	$packageId = '8c937b87-37d2-4a1d-8db6-4537502e58bf';
    $adminID = 'af6bf51d-426e-4a31-bcfc-1ecaf706b202';
    $token = 'zqk_7PDsHJmAopGV2FHDfqQk3Rnescp7ZZicCIpeiN_rx6T3jhdq0IjHKFv2tbx6EGBs0AQe0KXGWRQDk6wJ_DTSkxlV1Q8L-9wR77-W6gg7KAzK-fH_0WPuIyS1fo6Z82vYQx0F1Z3jDhMvWr1I8HnKzfeXgFwaN_E-SKTspidMwoZKMVIW9RXQ2dgRHp7oXG0-XvbgBY4ERLgm2NmFZmHjUo6Yp54LkCDc-vokISwhq5VKY_BNLw2o30E5llTk99VtBiUCfGS0xqkK9Z8fA5-r8-iuji_vRVY_Ja_yrp4-0m-iTlMoz7kDi_kGlO0HYmLYU7MWk_t3884jOCnYiCGNUOxccwPaDZ3A4jsOzkspDMsfrrXhcuUegHhzA_fNSSNoah4jych4LzNlBtz89ufMfV0bIPZBKVNDH5-vhttoCq0oW9q-ekMEOnsfjHshllvBEfY4KGq9AyRNZr1h2sYOYeSKwUq9_ZfyDkdpXBEiiZkv_se344TCG69zdWI2hFtwxFbGqHOJMvcyieb7kTr7uBZx5Ab1b6_CXawaPu_MdBeEJHxR2nctkv9cf6LN8YXNiSLJYKCm0r5r3fP-e9ca6UV9WazwkZZUNSzj6LgpOF3sdivY9auUs2sOdvq2OzcY-apGzypSZJTsevZYRkN4-88xBPXw8wtvJQGQwTBzd5ttFdTvQLnDH25qWin42POmZlN6g2MzgE-1e9jGzYWdMMgRkbetl7MTX8IYTcpWZR80';

    $contentBodyJson = file_get_contents('php://input');
	$content = json_decode($contentBodyJson, true);

    //perform SSO
    $url = $baseUrl . '/api/v2/sso';
    $merchantID = $content['merchantID'];
    $body = [
        'ExternalUserId' => $merchantID,
        'Email' => $merchantID.'@arcadier.com'
    ];
    
    $sso_response = callAPI('POST', $token, $url, $body);

    //check if user is a buyer or seller
    $url = $baseUrl.'/api/v2/users/'.$sso_response['AccessToken']['UserId'];
    $response = callAPI('GET', null, $url, false);

    //if SSO user is a seller, proceed
    if($response['Roles'][0] == 'Merchant'){ 
        $merchantID_arcadier_guid = $sso_response['AccessToken']['UserId'];
    }
    else{
        $url = $baseUrl . '/api/v2/admins/'.$adminID.'/users/'.$sso_response['AccessToken']['UserId'].'/roles/merchant';
        $response = upgrade_to_merchant($url, $token);
        
        if($response['AccessToken']['UserId'] == $sso_response['AccessToken']['UserId']){
            $merchantID_arcadier_guid = $response['AccessToken']['UserId'];
        }
    }
    

    //search custom table if item already exists
    $url = $baseUrl . '/api/v2/plugins/'.$packageId.'/custom-tables/item_records/';
    $body = [
        [
            'Name' => 'ringier_ID',
            'Operator' => 'eq',
            'Value' => $content['itemID']
        ]
    ];

    $response = callAPI('POST', null, $url, $body);
    
    if($response['TotalRecords'] == 0){
        //item does not exist
        print_r('Item does not exist. Creating one.\n');
        //create item
        $url = $baseUrl . '/api/v2/merchants/'.$merchantID_arcadier_guid.'/items';
        $body = [
            'SKU' => $content['sku'],
            'Name'=> $content['name'],
            'BuyerDescription'=> $content['description'],
            'SellerDescription'=> $content['description'],
            'Price'=> $content['price'],
            'StockLimited'=> true,
            'StockQuantity'=> $content['quantity'],
            'CurrencyCode' => 'SGD',
            'IsVisibleToCustomer' => true,
            'Active'=> true,
            'IsAvailable'=> true
        ];

        $item_response = callAPI('POST', $token, $url, $body);
        $item_ID = $item_response['ID'];

        //add this item to custom table
        $url = $baseUrl . '/api/v2/plugins/'. $packageId .'/custom-tables/item_records/rows';
        $body = [
            'arcadier_guid' => $item_ID,
            'ringier_ID' => $content['itemID']
        ];

        $response = callAPI('POST', null, $url, $body);

        if($response['arcadier_guid'] == $item_ID){
            //add item to cart
            $body = [
                'ItemDetail' => [
                    'ID' => $item_ID,
                ],        //column name in table
                'Quantity' => $content['quantity'],
                'CartItemType' => 'delivery'
            ];
            $url = $baseUrl . '/api/v2/users/f64e1e66-48ba-49e2-ac6c-39e911022d71/carts';
            $response = callAPI('POST', $token, $url, $body);

            echo json_encode($response);
        }
    }
    else{
        //item exists
        $item_ID = $response['Records'][0]['arcadier_guid'];

        $body = [
            'ItemDetail' => [
                'ID' => $item_ID,
            ],        //column name in table
            'Quantity' => $content['quantity'],
            'CartItemType' => 'delivery'
        ];
        $url = $baseUrl . '/api/v2/users/f64e1e66-48ba-49e2-ac6c-39e911022d71/carts';
        $response = callAPI('POST', $token, $url, $body);

        echo json_encode($response);
    }


function upgrade_to_merchant($url, $token){
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'PUT',
      CURLOPT_POSTFIELDS =>'{
        "Test": "nnone"
    }',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.$token,
        'Content-Type: application/json'
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    return json_decode($response, true);
    
}
    
?>