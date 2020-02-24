<?php
require __DIR__ . '/../vendor/autoload.php';

use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersResponse;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Endian;

$port = 502;
$ip = '192.168.0.131';

$connection = BinaryStreamConnection::getBuilder()
    ->setPort($port)
    ->setHost($ip)
    ->build();

try {
    // $packet = new ReadHoldingRegistersRequest($startAddress, $quantity, $unitId);
    $packet = new ReadHoldingRegistersRequest(0, 20, 0);
}
catch (Exception $e) {
    // Print error information if any
    echo $connection;
    echo $e;
    exit;
}

// Print read data
echo "</br>Data:</br>";
// print_r($recData); 
// print_r($packet); 
echo "</br>";

$result = [];
try {
    $binaryData = $connection->connect()->sendAndReceive($packet);
    $log[] = 'Binary received (in hex):   ' . unpack('H*', $binaryData)[1];

    /** @var $response ReadHoldingRegistersResponse */
    $response = ResponseFactory::parseResponseOrThrow($binaryData)->withStartAddress(0);

    foreach ($response as $address => $word) {
        $doubleWord = isset($response[$address + 1]) ? $response->getDoubleWordAt($address) : null;
        $quadWord = null;

        if (isset($response[$address + 3])) {
            $quadWord = $response->getQuadWordAt($address);
            try {
                $UInt64 = $quadWord->getUInt64(); // some data can not be converted to unsigned 64bit int due PHP memory limitations
            } catch (Exception $e) {
                $UInt64 = '-';
            }
            try {
                $Int64 = $quadWord->getInt64();
            } catch (Exception $e) {
                $Int64 = '-';
            }
        }

        $highByteAsInt = $word->getHighByteAsInt();
        $lowByteAsInt = $word->getLowByteAsInt();
        $result[$address] = [
            'highByte' => '0x' . str_pad(dechex($highByteAsInt), 2, '0') . ' / ' . $highByteAsInt . ' / "&#' . $highByteAsInt . ';"',
            'lowByte' => '0x' . str_pad(dechex($lowByteAsInt), 2, '0') . ' / ' . $lowByteAsInt . ' / "&#' . $lowByteAsInt . ';"',
            'highByteBits' => sprintf('%08d', decbin($highByteAsInt)),
            'lowByteBits' => sprintf('%08d', decbin($lowByteAsInt)),
            'int16' => $word->getInt16(),
            'UInt16' => $word->getUInt16(),
            'int32' => $doubleWord ? $doubleWord->getInt32() : null,
            'UInt32' => $doubleWord ? $doubleWord->getUInt32() : null,
            'float' => $doubleWord ? $doubleWord->getFloat() : null,
            'Int64' => $quadWord ? $Int64 : null,
            'UInt64' => $quadWord ? $UInt64 : null,
        ];
    }

} catch (Exception $exception) {
    $result = null;
    $log[] = 'An exception occurred';
    $log[] = $exception->getMessage();
    $log[] = $exception->getTraceAsString();
} finally {
    $connection->close();
}

echo '<pre>';
print_r($result); 
?>