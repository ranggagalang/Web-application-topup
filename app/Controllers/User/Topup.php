<?php

namespace App\Controllers\User;

use App\Models\UserModel;
use App\Models\TopupModel;
use App\Models\MetodePembayaranModel;
use App\Models\ApiProviderModel;
use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

class Topup extends BaseController
{
    protected $session;
    
    public function __construct()
    {
        $this->session = session();
        $this->userModel = new UserModel();
        $this->topupModel = new TopupModel();
    }
    
    public function prosesPayment()
    {
        $userLogin = $this->session->has('isLogin');
        $username = '';
        
        if ($userLogin) {
            $username = $this->session->get('username');
            $user = $this->userModel->where('username', $username)->first();
        } else {
          return redirect()->to('/');
        }
        
        $json = $this->request->getJSON();
        $nominal = html_entity_decode($json->nominal, ENT_QUOTES, 'UTF-8');
        $metodeCode = html_entity_decode($json->metodeCode, ENT_QUOTES, 'UTF-8');
        $metodeName = html_entity_decode($json->metodeName, ENT_QUOTES, 'UTF-8');
    
        $uniqueTopupID = false;
        $maxAttempts = 10;
    
        for ($i = 0; $i < $maxAttempts; $i++) {
            $topupID = rand(1000000, 9999999);
    
            $existingTopupID = $this->topupModel->where('topup_id', $topupID)->first();
    
            if (!$existingTopupID) {
                $uniqueTopupID = true;
                break;
            }
        }
    
        if (!$uniqueTopupID) {
            throw new \Exception('Gagal mendapatkan nomor pesanan setelah sejumlah percobaan, hubungi administrator!!.');
        }
    
        $settings = $this->getSettingsData();
        
        $apiProviderModel = new ApiProviderModel();
        $api = $apiProviderModel->where('kode', 'Pd')->first();
        
        $merchant = $api['api_id'];
        $metode = $metodeCode;
        $secret = $api['api_key'];
        $ref_id = $topupID;
        $nominal = $nominal;
        
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
                'user_id' => $user['id'],
                'username' => $user['username'],
                'topup_id' => $ref_id,
                'nominal' => $nominal,
                'fee' => $responseData['data']['total_bayar'] - $responseData['data']['total_diterima'],
                'total_pembayaran' => $responseData['data']['total_bayar'],
                'metode' => $metodeName,
                'kode_pembayaran' => $responseData['data']['nomor_va'] ?? $responseData['data']['checkout_url'] ?? $responseData['data']['qr_link'] ?? $responseData['data']['checkout_url'],
                'status' => 'Unpaid',
                'batas_pembayaran' => $batas_pembayaran,
                'cara_bayar' => $responseData['data']['panduan_pembayaran'],
            ];
    
            $this->topupModel->insert($data);
            
            if (isset($responseData['data'])) {
                return $this->response->setJSON(['success' => true, 'topupID' => $ref_id]);
            } else {
                $this->session->setFlashdata('error', 'Error: Data pembayaran tidak valid.');
            }
        } else {
            return $this->response->setJSON(['success' => false, 'message' => $responseData['error_msg']]);
        }
    }
    
}