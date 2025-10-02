<?php

namespace App\Support;

/**
 * Gera payload EMV para PIX “copia e cola” (BR Code).
 * Campos essenciais: chave, nome, cidade, valor, txid
 */
class Pix
{
    public static function payload(float $amount, string $txid, string $description = ''): string
    {
        $key   = env('PIX_KEY');                        // sua chave PIX
        $name  = mb_substr(env('PIX_MERCHANT_NAME','LOJA'), 0, 25);
        $city  = mb_strtoupper(mb_substr(env('PIX_MERCHANT_CITY','RECIFE'),0,15), 'UTF-8');

        if (!$key) {
            // sem chave → gera payload simples sem valor
            $amount = 0.0;
        }

        // TLV helpers
        $tlv = fn($id, $val) => sprintf('%02d%02d%s', $id, strlen($val), $val);

        // Merchant Account Information (ID 26)
        $mai  = $tlv(0, 'br.gov.bcb.pix');                   // 00 GUI
        if ($key)        $mai .= $tlv(1, $key);              // 01 chave
        if ($description)$mai .= $tlv(2, mb_substr($description,0,25)); // 02 descr.

        // Monta payload base (sem CRC ainda)
        $payload  = '';
        $payload .= $tlv(0, '01');                           // 00: Payload Format Indicator
        $payload .= $tlv(1, '12');                           // 01: Point of Initiation (12 = dinâmico, 11=estático)
        $payload .= $tlv(26, $mai);                          // 26: MAI - br.gov.bcb.pix
        $payload .= $tlv(52, '0000');                        // 52: MCC (0000)
        $payload .= $tlv(53, '986');                         // 53: BRL
        if ($amount > 0) {
            $val = number_format($amount, 2, '.', '');
            $payload .= $tlv(54, $val);                      // 54: Amount
        }
        $payload .= $tlv(58, 'BR');                          // 58: Country
        $payload .= $tlv(59, $name);                         // 59: Merchant Name (≤25)
        $payload .= $tlv(60, $city);                         // 60: Merchant City (≤15)

        // Additional Data Field (62): txid
        $txid = preg_replace('/[^A-Za-z0-9\-]/','', $txid);
        $txid = substr($txid, 0, 25);
        $adf  = $tlv(5, $txid);                              // 05: txid
        $payload .= $tlv(62, $adf);                          // 62: Additional Data Field

        // 63: CRC (com 04 + ‘0000’ como placeholder)
        $payload .= '6304';
        $payload .= self::crc16($payload);

        return $payload;
    }

    /** CRC-16/IBM (polinômio 0x1021), retorno em HEX maiúsculo */
    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($c = 0; $c < $len; $c++) {
            $crc ^= ord($data[$c]) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(sprintf('%04X', $crc));
    }
}
