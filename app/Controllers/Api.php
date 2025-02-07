<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ProdukModel;
use App\Models\PembelianModel;
use CodeIgniter\API\ResponseTrait;

class Api extends BaseController
{
    use ResponseTrait;

    protected $userModel;
    protected $produkModel;
    protected $pembelianModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->produkModel = new ProdukModel();
        $this->pembelianModel = new PembelianModel();
    }

    public function getProfile()
    {
        $apiId = $this->request->getPost('api_id');
        $apiKey = $this->request->getPost('api_key');
        $signature = $this->request->getPost('signature');
        $ipAddress = $this->request->getIPAddress();
        
        if (empty($apiId) || empty($apiKey)) {
            $response = [
                'status' => false,
                'msg' => 'API ID atau API KEY tidak ditemukan',
            ];
            return $this->respond($response);
        }

        $user = $this->userModel->findUserByApiKey($apiId, $apiKey);

        if (!$user) {
            $response = [
                'status' => false,
                'msg' => 'Pengguna dengan kredensial API yang diberikan tidak ditemukan',
            ];
            return $this->respond($response);
        }

        $expectedSignature = md5($apiId . $user['api_key']);
        if ($signature !== $expectedSignature) {
            $response = [
                'status' => false,
                'msg' => 'Signature Tidak Valid. Silakan periksa kredensial API Anda',
            ];
            return $this->respond($response);
        }

        // Periksa whitelist IP jika tidak kosong
        if (empty($user['whitelist_ip'])) {
            $whitelistIps = explode(',', $user['whitelist_ip']);
            if (!in_array($ipAddress, $whitelistIps)) {
                $response = [
                    'status' => false,
                    'msg' => "IP $ipAddress Tidak di Izin kan",
                ];
                return $this->respond($response);
            }
        }

        return $this->respond([
            'status' => true,
            'msg' => 'berhasil mendapatkan data profile',
            'data' => [
                'full_name' => $user['nama'],
                'username' => $user['username'],
                'email' => $user['email'],
                'balance' => $user['balance'],
                'role' => $user['role'],
                'created_at' => $user['date_create']
            ],
        ]);
    }

    public function getService()
    {
        $apiId = $this->request->getPost('api_id');
        $apiKey = $this->request->getPost('api_key');
        $signature = $this->request->getPost('signature');
        $ipAddress = $this->request->getIPAddress();
        
        if (empty($apiId) || empty($apiKey)) {
            $response = [
                'status' => false,
                'msg' => 'API ID atau API KEY Tidak Ditemukan',
            ];
            return $this->respond($response);
        }

        $user = $this->userModel->findUserByApiKey($apiId, $apiKey);

        if (!$user) {
            $response = [
                'status' => false,
                'msg' => 'Pengguna dengan kredensial API yang diberikan tidak ditemukan',
            ];
            return $this->respond($response);
        }

        $expectedSignature = md5($apiId . $user['api_key']);
        if ($signature !== $expectedSignature) {
            $response = [
                'status' => false,
                'msg' => 'Signature Tidak Valid. Silakan periksa kredensial API Anda',
            ];
            return $this->respond($response);
        }

        // Periksa whitelist IP jika tidak kosong
        if (empty($user['whitelist_ip'])) {
            $whitelistIps = explode(',', $user['whitelist_ip']);
            if (!in_array($ipAddress, $whitelistIps)) {
                $response = [
                    'status' => false,
                    'msg' => "IP $ipAddress tidak ada dalam whitelist",
                ];
                return $this->respond($response);
            }
        }
        
        $produk = $this->produkModel->findAll();
        
        $response = [];
        foreach ($produk as $product) {
            $response[] = [
                'status' => true,
                'msg' => 'berhasil mendapatkan data layanan',
                'data' => [
                    'id' => $product['id'],
                    'game' => $product['brand'],
                    'nama_layanan' => $product['nama'],
                    'harga' => [
                        'basic' => $product['harga_basic'],
                        'gold' => $product['harga_gold'],
                        'platinum' => $product['harga_platinum'],
                    ],
                    'status' => $product['status']
                ],
            ];
        }

        return $this->respond($response);
    }

    public function getStatus()
    {
        $apiId = $this->request->getPost('api_id');
        $apiKey = $this->request->getPost('api_key');
        $orderId = $this->request->getPost('order_id');
        $signature = $this->request->getPost('signature');
        $ipAddress = $this->request->getIPAddress();
        
        if (empty($apiId) || empty($apiKey)) {
            $response = [
                'status' => false,
                'msg' => 'API ID atau API KEY Tidak Ditemukan',
            ];
            return $this->respond($response);
        }

        $user = $this->userModel->findUserByApiKey($apiId, $apiKey);

        if (!$user) {
            $response = [
                'status' => false,
                'msg' => 'Pengguna dengan kredensial API yang diberikan tidak ditemukan',
            ];
            return $this->respond($response);
        }

        $expectedSignature = md5($apiId . $user['api_key']);
        if ($signature !== $expectedSignature) {
            $response = [
                'status' => false,
                'msg' => 'Signature Tidak Valid. Silakan periksa kredensial API Anda',
            ];
            return $this->respond($response);
        }

        // Periksa whitelist IP jika tidak kosong
        if (empty($user['whitelist_ip'])) {
            $whitelistIps = explode(',', $user['whitelist_ip']);
            if (!in_array($ipAddress, $whitelistIps)) {
                $response = [
                    'status' => false,
                    'msg' => "IP $ipAddress tidak ada dalam whitelist",
                ];
                return $this->respond($response);
            }
        }
        
        $dataOrder = $this->pembelianModel->findOrderByOrderId($orderId);

        if (!$dataOrder) {
          $response = [
              'status' => false,
              'msg' => 'order_id tidak ditemukan',
              'data' => [
                  'order_id' => '',
                  'status' => 'error',
              ],
          ];
          return $this->respond($response);
        }
        
        if ($dataOrder['games'] == 'Mobile Legends') {
        $response = [
            'status' => true,
            'msg' => 'Detail transaksi berhasil didapatkan',
            'data' => [
                'order_id' => $dataOrder['order_id'],
                'data_id' => $dataOrder['uid'] . ' (' . $dataOrder['server'] . ')',
                'service' => $dataOrder['produk'],
                'status' => $dataOrder['status_pembelian'],
                'note' => $dataOrder['note'],
                'price' => $dataOrder['harga_jual']
            ],
        ];

        return $this->respond($response);
        }
        
        $response = [
            'status' => true,
            'msg' => 'Detail transaksi berhasil didapatkan',
            'data' => [
                'order_id' => $dataOrder['order_id'],
                'data_id' => $dataOrder['uid'],
                'service' => $dataOrder['produk'],
                'status' => $dataOrder['status_pembelian'],
                'note' => $dataOrder['note'],
                'price' => $dataOrder['harga_jual']
            ],
        ];

        return $this->respond($response);
        
        /*$server = ($dataOrder['server'] === 'NoServer') ? '' : '(' . $dataOrder['server'] . ')';
        
        $response = [
            'status' => true,
            'msg' => 'Detail transaksi berhasil didapatkan',
            'data' => [
                'order_id' => $dataOrder['order_id'],
                'data_id' => $dataOrder['uid'] . $server,
                'service' => $dataOrder['produk'],
                'status' => $dataOrder['status_pembelian'],
                'note' => $dataOrder['note'],
                'price' => $dataOrder['harga_jual']
            ],
        ];

        return $this->respond($response);*/
    }

    public function order()
    {
        $apiId = $this->request->getPost('api_id');
        $apiKey = $this->request->getPost('api_key');
        $orderId = $this->request->getPost('order_id');
        $serviceId = $this->request->getPost('service_id');
        $targetId = $this->request->getPost('target_id');
        $targetServer = $this->request->getPost('target_server');
        $signature = $this->request->getPost('signature');
        $ipAddress = $this->request->getIPAddress();
        
        if (empty($apiId) || empty($apiKey)) {
            $response = [
                'status' => false,
                'msg' => 'API ID atau API KEY Tidak Ditemukan',
            ];
            return $this->respond($response);
        }

        $user = $this->userModel->findUserByApiKey($apiId, $apiKey);

        if (!$user) {
            $response = [
                'status' => false,
                'msg' => 'Pengguna dengan kredensial API yang diberikan tidak ditemukan',
            ];
            return $this->respond($response);
        }

        $expectedSignature = md5($apiId . $user['api_key']);
        if ($signature !== $expectedSignature) {
            $response = [
                'status' => false,
                'msg' => 'Signature Tidak Valid. Silakan periksa kredensial API Anda',
            ];
            return $this->respond($response);
        }

        // Periksa whitelist IP jika tidak kosong
        if (empty($user['whitelist_ip'])) {
            $whitelistIps = explode(',', $user['whitelist_ip']);
            if (!in_array($ipAddress, $whitelistIps)) {
                $response = [
                    'status' => false,
                    'msg' => "IP $ipAddress tidak ada dalam whitelist",
                ];
                return $this->respond($response);
            }
        }
        
        $dataOrder = $this->pembelianModel->where('order_id', $orderId)->first();

        if ($dataOrder) {
          $response = [
              'status' => false,
              'msg' => 'order_id sudah tersedia pada sistem kami',
              'data' => [
                  'status' => 'error',
              ],
          ];
          return $this->respond($response);
        }
        
        $produk = $this->produkModel->where('id', $serviceId)->first();

        if (!$produk) {
            $response = [
                'status' => false,
                'msg' => 'service_id tidak ditemukan',
            ];
            return $this->respond($response);
        }

        switch ($user['role']) {
            case 'Basic':
                $hargaProduk = $produk['harga_basic'];
                break;
            case 'Gold':
                $hargaProduk = $produk['harga_gold'];
                break;
            case 'Platinum':
            case 'admin':
                $hargaProduk = $produk['harga_platinum'];
                break;
            default:
                $hargaProduk = $produk['harga_basic'];
                break;
        }

        if ($user['balance'] < $hargaProduk) {
            $response = [
                'status' => false,
                'msg' => 'Saldo anda tidak mencukupi',
            ];
            return $this->respond($response);
        } else {

          if (in_array($produk['provider'], ['Vip', 'DF', 'AG', 'Manual'])) {

            $newBalance = floatval($user['balance']) - floatval($hargaProduk);
            $this->userModel->update($user['id'], ['balance' => $newBalance]);

            $dataOrder = [
                'user_id' => $user['id'],
                'order_id' => $orderId,
                'games' => $produk['brand'],
                'produk' => $produk['nama'],
                'kode_produk' => $produk['kode_produk'],
                'uid' => $targetId,
                'server' => $targetServer,
                'nama_target' => 'Order Via API',
                'harga_provider' => $produk['harga_provider'],
                'harga_jual' => $hargaProduk,
                'keuntungan' => $hargaProduk - $produk['harga_provider'],
                'fee' => '',
                'total_pembayaran' => $hargaProduk,
                'provider' => $produk['provider'],
                'metode_pembayaran' => 'Saldo User (API)',
                'kode_pembayaran' => 'Pembelian via API',
                'status_pembayaran' => 'Paid',
                'status_pembelian' => 'Proses',
                'nomor_whatsapp' => $user['whatsapp'],
                'note' => '',
              ];

            $this->pembelianModel->insert($dataOrder);
          } else {
            $dataOrder = [
                'user_id' => $user['id'],
                'order_id' => $orderId,
                'games' => $produk['brand'],
                'produk' => $produk['nama'],
                'kode_produk' => $produk['kode_produk'],
                'uid' => $targetId,
                'server' => $targetServer,
                'nama_target' => '-',
                'harga_provider' => $produk['harga_provider'],
                'harga_jual' => $hargaProduk,
                'keuntungan' => $hargaProduk - $produk['harga_provider'],
                'fee' => '',
                'total_pembayaran' => $hargaProduk,
                'provider' => $produk['provider'],
                'metode_pembayaran' => 'Saldo User (API)',
                'kode_pembayaran' => 'Pembelian via API',
                'status_pembayaran' => 'Paid',
                'status_pembelian' => 'Refund',
                'nomor_whatsapp' => $user['whatsapp'],
                'note' => 'Provider tidak di temukan, hubungi administrator!',
              ];

            $this->pembelianModel->insert($dataOrder);
          }

        }

        $response = [
            'status' => true,
            'msg' => 'Pesanan berhasil! Pesanan sedang diproses',
            'data' => [
                'order_id' => $orderId,
                'nama_layanan' => $produk['nama'],
                'service_id' => $serviceId,
                'target_id' => $targetId,
                'target_server' => $targetServer,
                'status' => 'Proses',
                'note' => '',
            ],
        ];

        return $this->respond($response);
    }
}
