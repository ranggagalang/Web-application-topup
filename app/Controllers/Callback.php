<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Controllers\BaseController;
use App\Models\PembelianModel;
use App\Models\TopupModel;
use App\Models\UserModel;
use App\Models\ApiProviderModel;

class Callback extends BaseController
{
    public function __construct()
    {
        $this->pembelianModel = new PembelianModel();
        $this->pembelianModel = new TopupModel();
    }
    
    public function callbackTokopay()
    {
        $settings = $this->getSettingsData();
        
        $apiProviderModel = new ApiProviderModel();
        $apiPd = $apiProviderModel->where('kode', 'Pd')->first();
        
        $merchant_id = $apiPd['api_id'];
        $secret_key = $apiPd['api_key'];
        
        $callbackIP = '178.128.104.179';
    
        if ($_SERVER['REMOTE_ADDR'] !== $callbackIP) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        
        $request = service('request');
        $json = $request->getBody();
        $data = json_decode($json, true);
        
        if (isset($data['status'], $data['reff_id'], $data['signature'])) {
            $status = $data['status'];
            if ($status === "Success") {
                //hanya proses yg status transaksi sudah di bayar, sukses = dibayar
                $ref_id = $data['reff_id'];
                /*
                 * Validasi Signature
                 */
                $signature_from_tokopay = $data['signature'];
                $signature_validation = md5($merchant_id . ":" . $secret_key . ":" . $ref_id);
                
                if ($signature_from_tokopay === $signature_validation) {

                  $pembelianModel = new PembelianModel();
                  $invoice = $pembelianModel
                      ->where('order_id', $ref_id)
                      ->where('status_pembayaran', 'Unpaid')
                      ->first();
          
                  if ($invoice) {
                      
                      $orderData = [
                        'status_pembayaran' => 'Paid',
                        'status_pembelian' => 'Proses',
                        'note' => 'Pembelian sedang di proses',
                      ];
          
                     $updateStatus = $pembelianModel->update($invoice['id'], $orderData);
                      
                      if ($invoice['provider'] == 'Vip') {
                          $apiVip = $apiProviderModel->where('kode', 'Vip')->first();
                          
                          $apiId = $apiVip['api_id'];
                          $apiKey = $apiVip['api_key'];
                          $sign = md5($apiId . $apiKey);
                      
                          $data = [
                              'key' => $apiKey,
                              'sign' => $sign,
                              'type' => 'order',
                              'service' => $invoice['kode_produk'],
                              'data_no' => $invoice['uid'],
                              'data_zone' => ($invoice['server'] == 'NoServer') ? '' : $invoice['server'],
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
                              $postDataApi = [
                                  'trx_id' => $responseData['data']['trxid'],
                              ];
                      
                              $pembelianModel->update($invoice['id'], $postDataApi);
                      
                              $result = ['success' => true, 'message' => 'Berhasil mendapatkan data dari API.'];
                          } else {
                              $result = ['success' => false, 'message' => 'Gagal mendapatkan data dari API.'];
                          }
                      } elseif ($invoice['provider'] == 'DF') {
                          $apiDF = $apiProviderModel->where('kode', 'DF')->first();
                          
                          $userdigi = $apiDF['api_id'];
                          $apiKey = $apiDF['api_key'];
                      
                          $postData = [
                              'username' => $userdigi,
                              'buyer_sku_code' => $invoice['kode_produk'],
                              'customer_no' => ($invoice['server'] === 'NoServer') ? strval($invoice['uid']) : strval($invoice['uid']) . strval($invoice['server']),
                              'ref_id' => $invoice['order_id'],
                              'sign' => md5($userdigi . $apiKey . strval($invoice['order_id'])),
                          ];
                      
                          $ch = curl_init('https://api.digiflazz.com/v1/transaction');
                          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                          curl_setopt($ch, CURLOPT_POST, 1);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                          curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                      
                          $response = curl_exec($ch);
                          $responseData = json_decode($response, true);
                      
                          curl_close($ch);
                      
                          if (isset($responseData['data'])) {
                              $orderData = [
                                  'note' => $responseData['data']['message'],
                              ];
                      
                              $pembelianModel->update($invoice['id'], $orderData);
                          } else {
                              $result = ['success' => false, 'message' => 'Gagal mendapatkan data dari API.'];
                          }
                      } elseif ($invoice['provider'] == 'AG') {
                          $apiAG = $apiProviderModel->where('kode', 'AG')->first();
                          
                          $merchant_id = $apiAG['api_id'];
                          $secret_key = $apiAG['api_key'];
                          
                          $postData = [
                              'ref_id' => strval($invoice['order_id']),
                              'merchant_id' => $merchant_id,
                              'produk' => strval($invoice['kode_produk']),
                              'tujuan' => strval($invoice['uid']),
                              'server_id' => ($invoice['server'] == 'NoServer') ? '' : strval($invoice['server']),
                              'signature' => md5($merchant_id . ':' . $secret_key . ':' . strval($invoice['order_id'])),
                          ];
                      
                          $ch = curl_init('https://v1.apigames.id/v2/transaksi');
                          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                          curl_setopt($ch, CURLOPT_POST, 1);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                          curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                      
                          $response = curl_exec($ch);
                          $responseData = json_decode($response, true);
                      
                          curl_close($ch);
                      
                          if (isset($responseData['data'])) {
                              $orderData = [
                                  'trx_id' => $responseData['data']['trx_id'],
                                  'note' => 'Transaksi sedang diproses',
                              ];
                      
                              $pembelianModel->update($invoice['id'], $orderData);
                          } else {
                              $result = ['success' => false, 'message' => 'Gagal mendapatkan data dari API.'];
                          }
                      } elseif ($invoice['provider'] == 'Manual') {
                    
                        $orderData = [
                            'note' => 'Pesanan sedang di proses',
                        ];
                
                        $pembelianModel->update($invoice['id'], $orderData);
                        
                      } else {
                          $orderData = [
                              'status_pembelian' => 'Gagal',
                              'note' => 'Provider tidak di temukan',
                          ];
                      
                          $pembelianModel->update($invoice['id'], $orderData);
                      
                          $result = ['success' => false, 'message' => 'Provider tidak di temukan'];
                      }
                      
                      
                      $whatsappMessage = "*{$settings['web_title']}*\n\n";
                      $whatsappMessage .= "Pembayaran pesanan {$invoice['order_id']} *Terkonfirmasi* saat ini pesanan anda sedang di *Proses*\n";
                      $whatsappMessage .= "---------------------------\n\n";
                      $whatsappMessage .= "*Lihat Pesanan*\n" . base_url('/invoice/' . $invoice['order_id']) . "\n\n";
                      $whatsappMessage .= "*Terimakasih!*";
                      
                      $whatsapp = $invoice['nomor_whatsapp'];
                      
                      $this->sendUserWhatsappMessage($whatsapp, $whatsappMessage);
                      
                      $whatsappAdminMessage = "*{$settings['web_title']}*\n\n";
                      $whatsappAdminMessage .= "Pembayaran pesanan {$invoice['order_id']} *Terkonfirmasi* saat ini pesanan sedang di *Proses*\n";
                      $whatsappAdminMessage .= "---------------------------\n\n";
                      $whatsappAdminMessage .= "*Lihat Pesanan*\n" . base_url('/invoice/' . $invoice['order_id']) . "\n\n";
                      $whatsappAdminMessage .= "*Terimakasih!*";
                      
                      $whatsappAdmin = $settings['whatsapp_admin'];
                      
                      $this->sendAdminWhatsappMessage($whatsappAdmin, $whatsappAdminMessage);
          
                  } else {
                      $topupModel = new TopupModel();
                      $topup = $topupModel
                          ->where('topup_id', $ref_id)
                          ->where('status', 'Unpaid')
                          ->first();
                          
                          if ($topup) {
                            $userModel = new UserModel();
                            $user = $userModel->find($topup['user_id']);
                            
                            $newBalance = $user['balance'] + $topup['nominal'];
                            
                            $updateUserBalance = $userModel->update($topup['user_id'], ['balance' => $newBalance]);
                            
                            $updateStatusTopup = $topupModel->update($topup['id'], ['status' => 'PAID']);
                            
                          } else {
                            $topupData = [
                                'status' => 'Gagal',
                            ];
                        
                            $topupModel->update($topup['id'], $topupData);
                        
                            $result = ['success' => false, 'message' => 'Top Up gagal di lakukan'];
                        }
                          
                  }

                    return $this->respond(['status' => true]);
                } else {
                    return $this->respond(['error' => "Invalid Signature"]);
                }
            } else {
                return $this->respond(['error' => "Status payment tidak success"]);
            }
        } else {
            return $this->respond(['error' => "Data json tidak sesuai"]);
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
    
    private function sendAdminWhatsappMessage($whatsappAdmin, $whatsappAdminMessage)
    {
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Ft')->first();
        
        $curl = curl_init();
    
        $data = array(
            'target' => $whatsappAdmin,
            'message' => $whatsappAdminMessage,
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