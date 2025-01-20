<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\ProdukModel;
use App\Models\PembelianModel;
use App\Models\ApiProviderModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Log\Logger;

class Order extends BaseController
{

    public function __construct()
    {
        $this->session = \Config\Services::session();
        $this->pembelianModel = new PembelianModel();
        $this->userModel = new UserModel();
    }
    
    public function cekID()
    {
        $settings = $this->getSettingsData();
        
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Vip')->first();
        
        $apiId = $api['api_id'];
        $apiKey = $api['api_key'];
    
        $json = $this->request->getJSON();
    
        $uid = $json->uid;
        $server = $json->server;
        $target = $json->target;
    
        if (empty($uid) || empty($server)) {
            return $this->response->setJSON(['error' => 'UID dan server harus diisi.']);
        }
    
        $data = [
            'key' => $apiKey,
            'sign' => md5($apiId . $apiKey),
            'type' => 'get-nickname',
            'code' => $target,
            'target' => $uid,
            'additional_target' => $server,
        ];
    
        $url = 'https://vip-reseller.co.id/api/game-feature';
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
        $result = curl_exec($ch);
    
        if (curl_errno($ch)) {
            $errorMessage = 'Kesalahan Curl: ' . curl_error($ch);
            return $this->response->setJSON(['error' => $errorMessage]);
        }
    
        curl_close($ch);
    
        $result = json_decode($result, true);
    
        if ($result['result']) {
            return $this->response->setJSON(['responseData' => $result['data']]);
        } else {
            $errorMessage = isset($result['message']) ? $result['message'] : 'Error: Terjadi kesalahan. Silakan coba lagi nanti.';
            return $this->response->setJSON(['error' => $errorMessage]);
        }
    }
    
    public function payment()
    {
        $userModel = new UserModel();
        
        $userLogin = $this->session->has('isLogin');
        $username = '';
        
        if ($userLogin) {
            $username = $this->session->get('username');
            $user = $this->userModel->where('username', $username)->first();
        } else {
            $user = null;
        }
        
        $json = $this->request->getJSON();
        $uid = strip_tags(htmlspecialchars(html_entity_decode($json->uid, ENT_QUOTES, 'UTF-8')));
        $server = strip_tags(htmlspecialchars(html_entity_decode($json->server, ENT_QUOTES, 'UTF-8')));
        $username = strip_tags(htmlspecialchars(html_entity_decode($json->username, ENT_QUOTES, 'UTF-8')));
        $productPrice = strip_tags(htmlspecialchars(html_entity_decode($json->productPrice, ENT_QUOTES, 'UTF-8')));
        $productName = strip_tags(htmlspecialchars(html_entity_decode($json->productName, ENT_QUOTES, 'UTF-8')));
        $productCode = strip_tags(htmlspecialchars(html_entity_decode($json->productCode, ENT_QUOTES, 'UTF-8')));
        $metodeCode = strip_tags(htmlspecialchars(html_entity_decode($json->metodeCode, ENT_QUOTES, 'UTF-8')));
        $metodeName = strip_tags(htmlspecialchars(html_entity_decode($json->metodeName, ENT_QUOTES, 'UTF-8')));
        $whatsapp = strip_tags(htmlspecialchars(html_entity_decode($json->whatsapp, ENT_QUOTES, 'UTF-8')));
        
        $produkModel = new ProdukModel();
        $produk = $produkModel->where('kode_produk', $productCode)->first();
        $games = $produk['brand'];
        $hargaProvider = $produk['harga_provider'];
        $keuntungan = $produk['keuntungan'];
        $provider = $produk['provider'];
        
        if ($productPrice == $produk['harga_jual']) {
            $hargaJual = $produk['harga_jual'];
        } elseif ($productPrice == $produk['harga_basic']) {
            $hargaJual = $produk['harga_basic'];
        } elseif ($productPrice == $produk['harga_gold']) {
            $hargaJual = $produk['harga_gold'];
        } elseif ($productPrice == $produk['harga_platinum']) {
            $hargaJual = $produk['harga_platinum'];
        } else {
            $hargaJual = $produk['harga_jual'];
        }
        
    
        $uniqueOrderID = false;
        $maxAttempts = 10;
    
        for ($i = 0; $i < $maxAttempts; $i++) {
            $orderID = strval(mt_rand(100000, 999999));
    
            $existingOrderID = $this->pembelianModel->where('order_id', $orderID)->first();
    
            if (!$existingOrderID) {
                $uniqueOrderID = true;
                break;
            }
        }
        
        if (!$uniqueOrderID) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal mendapatkan nomor pesanan setelah sejumlah percobaan, hubungi administrator!!.']);
        }
        
        $orderID = strval($orderID);
    
        $settings = $this->getSettingsData();
        
        if ($metodeCode == '123') {
          
          if (!$this->session->has('isLogin')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Anda harus login untuk menggunakan metode pembayaran Saldo!']);
          }
          
          if ($user['balance'] < $hargaJual) {
              return $this->response->setJSON(['success' => false, 'message' => 'Saldo anda tidak mencukupi']);
          } else {
            $apiProviderModel = new ApiProviderModel();
            
            if ($produk['provider'] == 'Vip') {
                $apiVip = $apiProviderModel->where('kode', 'Vip')->first();
                
                $apiId = $apiVip['api_id'];
                $apiKey = $apiVip['api_key'];
                $sign = md5($apiId . $apiKey);
            
                $data = [
                    'key' => $apiKey,
                    'sign' => $sign,
                    'type' => 'order',
                    'service' => $produk['kode_produk'],
                    'data_no' => $uid,
                    'data_zone' => ($server == 'NoServer') ? '' : $server,
                ];
            
                $url = 'https://vip-reseller.co.id/api/game-feature';
            
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            
                $result = curl_exec($ch);
                $responseData = json_decode($result, true);
            
                curl_close($ch);
            
                if (isset($responseData['data'])) {
                    $data = [
                        'user_id' => $user['id'],
                        'order_id' => $orderID,
                        'trx_id' => $responseData['data']['trxid'],
                        'games' => $games,
                        'produk' => $produk['nama'],
                        'kode_produk' => $produk['kode_produk'],
                        'uid' => $uid,
                        'server' => $server,
                        'nama_target' => $username,
                        'harga_provider' => $produk['harga_provider'],
                        'harga_jual' => $hargaJual,
                        'keuntungan' => $keuntungan,
                        'fee' => 0,
                        'total_pembayaran' => $hargaJual,
                        'provider' => $provider,
                        'metode_pembayaran' => $metodeName,
                        'kode_pembayaran' => 'Pembayaran via saldo',
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'status_refund' => 'gagal',
                        'nomor_whatsapp' => $whatsapp,
                        'note' => 'Transaksi sedang di proses',
                    ];
              
                    $this->pembelianModel->insert($data);
                    
                    $newBalance = floatval($user['balance']) - floatval($hargaJual);
                    $userModel->update($user['id'], ['balance' => $newBalance]);
              
                    return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                } else {
                    return $this->response->setJSON(['success' => false, 'message' => 'Gagal kesalahan Provider, msg: ' . $responseData['message']]);
                }
            } elseif ($produk['provider'] == 'DF') {
                $apiDF = $apiProviderModel->where('kode', 'DF')->first();
                
                $userdigi = $apiDF['api_id'];
                $apiKey = $apiDF['api_key'];
            
                $postData = [
                    'username' => $userdigi,
                    'buyer_sku_code' => $produk['kode_produk'],
                    'customer_no' => ($server === 'NoServer') ? strval($uid) : strval($uid) . strval($server),
                    'ref_id' => $orderID,
                    'sign' => md5($userdigi . $apiKey . strval($orderID)),
                ];
            
                $ch = curl_init('https://api.digiflazz.com/v1/transaction');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
                $response = curl_exec($ch);
                $responseData = json_decode($response, true);
            
                if (isset($responseData['data'])) {
                    
                    $data = [
                        'user_id' => $user['id'],
                        'order_id' => $orderID,
                        'games' => $games,
                        'produk' => $produk['nama'],
                        'kode_produk' => $produk['kode_produk'],
                        'uid' => $uid,
                        'server' => $server,
                        'nama_target' => $username,
                        'harga_provider' => $produk['harga_provider'],
                        'harga_jual' => $hargaJual,
                        'keuntungan' => $keuntungan,
                        'fee' => 0,
                        'total_pembayaran' => $hargaJual,
                        'provider' => $provider,
                        'metode_pembayaran' => $metodeName,
                        'kode_pembayaran' => 'Pembayaran via saldo',
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'status_refund' => 'gagal',
                        'nomor_whatsapp' => $whatsapp,
                        'note' => $responseData['data']['message'],
                    ];
              
                    $this->pembelianModel->insert($data);
                    
                    $newBalance = floatval($user['balance']) - floatval($hargaJual);
                    $userModel->update($user['id'], ['balance' => $newBalance]);
                    
                    return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                    
                } else {
                  return $this->response->setJSON(['success' => false, 'message' => 'Gagal kesalahan Provider, msg: ' . $responseData['data']['rc']]);
                }
            } elseif ($produk['provider'] == 'AG') {
                $apiAG = $apiProviderModel->where('kode', 'AG')->first();
                
                $merchant_id = $apiAG['api_id'];
                $secret_key = $apiAG['api_key'];
            
                $postData = [
                    'ref_id' => $orderID,
                    'merchant_id' => $merchant_id,
                    'produk' => $produk['kode_produk'],
                    'tujuan' => $uid,
                    'server_id' => ($server == 'NoServer') ? '' : $server,
                    'signature' => md5($merchant_id . ':' . $secret_key . ':' . $orderID),
                ];
            
                $ch = curl_init('https://v1.apigames.id/v2/transaksi');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
                $response = curl_exec($ch);
                $responseData = json_decode($response, true);
            
                if (isset($responseData['data'])) {
                    
                    $data = [
                        'user_id' => $user['id'],
                        'order_id' => $orderID,
                        'trx_id' => $responseData['data']['trx_id'],
                        'games' => $games,
                        'produk' => $produk['nama'],
                        'kode_produk' => $produk['kode_produk'],
                        'uid' => $uid,
                        'server' => $server,
                        'nama_target' => $username,
                        'harga_provider' => $produk['harga_provider'],
                        'harga_jual' => $hargaJual,
                        'keuntungan' => $keuntungan,
                        'fee' => 0,
                        'total_pembayaran' => $hargaJual,
                        'provider' => $provider,
                        'metode_pembayaran' => $metodeName,
                        'kode_pembayaran' => 'Pembayaran via saldo',
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'status_refund' => 'gagal',
                        'nomor_whatsapp' => $whatsapp,
                        'note' => 'Transaksi sedang diproses',
                    ];
              
                    $this->pembelianModel->insert($data);
                    
                    $newBalance = floatval($user['balance']) - floatval($hargaJual);
                    $userModel->update($user['id'], ['balance' => $newBalance]);
                    
                    return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                    
                } else {
                  return $this->response->setJSON(['success' => false, 'message' => 'Gagal kesalahan Provider, msg: ' . $responseData['error_msg']]);
                }
            } elseif ($produk['provider'] == 'Manual') {
                    
                $data = [
                    'user_id' => $user['id'],
                    'order_id' => $orderID,
                    'games' => $games,
                    'produk' => $produk['nama'],
                    'kode_produk' => $produk['kode_produk'],
                    'uid' => $uid,
                    'server' => $server,
                    'nama_target' => $username,
                    'harga_provider' => $produk['harga_provider'],
                    'harga_jual' => $hargaJual,
                    'keuntungan' => $keuntungan,
                    'fee' => 0,
                    'total_pembayaran' => $hargaJual,
                    'provider' => $provider,
                    'metode_pembayaran' => $metodeName,
                    'kode_pembayaran' => 'Pembayaran via saldo',
                    'status_pembayaran' => 'Paid',
                    'status_pembelian' => 'Proses',
                    'status_refund' => 'gagal', 
                    'nomor_whatsapp' => $whatsapp,
                    'note' => 'Pesanan sedang di proses',
                ];
          
                $this->pembelianModel->insert($data);
                
                $newBalance = floatval($user['balance']) - floatval($hargaJual);
                $userModel->update($user['id'], ['balance' => $newBalance]);
                
                return $this->response->setJSON(['success' => true, 'orderID' => $orderID]);
                    
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Provider tidak di temukan, hubungi administrator!.']);
            }
            
          }
          
        } else {
          
          $apiProviderModel = new ApiProviderModel();
          $api = $apiProviderModel->where('kode', 'Pd')->first();
          
          $merchant = $api['api_id'];
          $metode = $metodeCode;
          $secret = $api['api_key'];
          $ref_id = $orderID;
          $nominal = $hargaJual;
          
          $ch = curl_init();
          
          $apiUrl = "https://api.tokopay.id";
          
          $url = $apiUrl."/v1/order?merchant=$merchant&secret=$secret&ref_id=$ref_id&nominal=$nominal&metode=$metode";
          
          curl_setopt_array($ch, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'GET',
          ));
          
          $response = curl_exec($ch);
          
          if (curl_errno($ch)) {
              echo 'Error:' . curl_error($ch);
          }
      
          curl_close($ch);
      
          $responseData = json_decode($response, true);
          
          if (isset($responseData['data'])) {
            
            $batas_pembayaran_creat = new \DateTime();
            $batas_pembayaran_creat->modify('+30 minutes');
            $batas_pembayaran = $batas_pembayaran_creat->format('Y-m-d H:i:s');
            
              $data = [
                  'user_id' => empty($user['id']) ? "Tidak Login" : $user['id'],
                  'order_id' => $ref_id,
                  'games' => $games,
                  'produk' => $produk['nama'],
                  'kode_produk' => $produk['kode_produk'],
                  'uid' => $uid,
                  'server' => $server,
                  'nama_target' => $username,
                  'harga_provider' => $produk['harga_provider'],
                  'harga_jual' => $hargaJual,
                  'keuntungan' => $keuntungan,
                  'fee' => $responseData['data']['total_bayar'] - $responseData['data']['total_diterima'],
                  'total_pembayaran' => $responseData['data']['total_bayar'],
                  'provider' => $provider,
                  'metode_pembayaran' => $metodeName,
                  'kode_pembayaran' => $responseData['data']['nomor_va'] ?? $responseData['data']['checkout_url'] ?? $responseData['data']['qr_link'] ?? $responseData['data']['checkout_url'],
                  'status_pembayaran' => 'Unpaid',
                  'batas_pembayaran' => $batas_pembayaran,
                  'cara_bayar' => $responseData['data']['panduan_pembayaran'],
                  'status_pembelian' => 'Pending',
                  'nomor_whatsapp' => $whatsapp,
                  'note' => '',
              ];
      
              $this->pembelianModel->insert($data);
              
              $whatsappMessage = "*{$settings['web_title']}*\n\n";
              $whatsappMessage .= "*Detail Pesanan*\n";
              $whatsappMessage .= "---------------------------\n";
              $whatsappMessage .= "*Invoice*: {$ref_id}\n";
              $whatsappMessage .= "*Produk*: {$produk['nama']}\n";
              $hargaProduk = number_format($nominal, 0, ',', '.');
              $whatsappMessage .= "*Harga*: Rp {$hargaProduk}\n";
              $whatsappMessage .= "*Status*: Menunggu Pembayaran\n";
              $whatsappMessage .= "---------------------------\n\n";
              $whatsappMessage .= "*Data Tujuan*\n";
              $whatsappMessage .= "---------------------------\n";
              $whatsappMessage .= "*ID*: {$uid}\n";
              if ($server !== 'NoServer') {
                  $whatsappMessage .= "*Server*: {$server}\n";
              }
              if ($username !== 'Off') {
                  $whatsappMessage .= "*Nickname*: {$username}\n";
              }
              $whatsappMessage .= "---------------------------\n\n";
              $whatsappMessage .= "Harap lakukan pembayaran agar pesanan anda dapat di proses.\n\n";
              $whatsappMessage .= "*Lihat Pesanan*\n" . base_url('/invoice/' . $ref_id) . "\n\n";
              $whatsappMessage .= "*Terimakasih!*";
              
              $this->sendUserWhatsappMessage($whatsapp, $whatsappMessage);
      
              if (isset($responseData['data'])) {
                  return $this->response->setJSON(['success' => true, 'orderID' => $ref_id]);
              } else {
                  return $this->response->setJSON(['success' => false, 'message' => 'Error: Data pembayaran tidak valid.']);
              }
          } else {
              return $this->response->setJSON(['success' => false, 'message' => $responseData['error_msg']]);
          }
        
        }
        
    }
    
    private function sendUserWhatsappMessage($whatsapp, $whatsappMessage)
    {
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Ft')->first();
        
        $curl = curl_init();
    
        $data = array(
            'target' => $whatsapp,
            'message' => $whatsappMessage,
        );
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array('Authorization: ' . $api['api_key']),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        
        return $response;
    }
    
}