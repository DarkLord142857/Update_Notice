<?php
// Cấu hình bot
$botToken = "7665676937:AAG2NzhfyiWcUXRusJ7tkxdYckzH6JyXmec";  // Thay token bot của bạn
$chatId = "-1002695949460";  // Thay chat ID của bạn
$url = "https://games.cagboot.com/directory.php?id=16";  // Link cần theo dõi
$jsonFile = "notification.json"; // File lưu thông báo

// Lấy nội dung trang web
$html = file_get_contents($url);
if (!$html) {
    die("Không thể lấy dữ liệu từ trang web.");
}

// Tạo đối tượng DOM
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$rows = $xpath->query("//tr");

// Đọc dữ liệu từ file JSON (nếu có)
$existingData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $existingData = json_decode($jsonContent, true);
    if (!is_array($existingData)) {
        $existingData = [];
    }
}

$existingIds = array_column($existingData, 'id');

foreach ($rows as $row) {
    if ($row instanceof DOMElement) {
        $columns = $row->getElementsByTagName("td");
    } else {
        continue;
    }

    if ($columns->length >= 5) {
        $id = trim($columns->item(0)->textContent);
        $gameName = trim($columns->item(1)->textContent);
        $size = trim($columns->item(2)->textContent);
        $date = trim($columns->item(3)->textContent);
        $status = trim($columns->item(4)->textContent);

        // Kiểm tra xem đã gửi thông báo chưa
        $found = false;
        foreach ($existingData as $entry) {
            if ($entry['id'] == $id && $entry['date'] == $date) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Gửi tin nhắn Telegram
            $message = "🎮 *Game mới cập nhật:*\n";
            $message .= "🆔 *ID:* $id\n";
            $message .= "🎮 *Tên:* $gameName\n";
            $message .= "📂 *Kích thước:* $size\n";
            $message .= "📅 *Ngày cập nhật:* $date\n";

            $telegramApi = "https://api.telegram.org/bot$botToken/sendMessage";
            $dataTelegram = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ];

            $attempts = 0;
            $maxAttempts = 5;
            $sent = false;

            while ($attempts < $maxAttempts && !$sent) {
                $result = sendTelegramMessage($telegramApi, $dataTelegram);
                if ($result['status'] === "success") {
                    echo "Đã gửi thông báo cho $gameName\n";
                    $sent = true;

                    // Lưu vào JSON
                    $newEntry = [
                        "id" => $id,
                        "game" => $gameName,
                        "size" => $size,
                        "date" => $date,
                        "status" => $status,
                    ];
                    $existingData[] = $newEntry;
                    file_put_contents($jsonFile, json_encode($existingData, JSON_PRETTY_PRINT));
                } elseif ($result['code'] == 429) {
                    echo "Lỗi 429: Quá nhiều request! Chờ 5 giây...\n";
                    sleep(5);
                } else {
                    echo "Gửi tin nhắn thất bại! Thử lại...\n";
                    sleep(2);
                }
                $attempts++;
            }

            // Giảm tải API bằng cách đợi giữa các request
            usleep(500000); // Dừng 0.5 giây
        } else {
            echo "Game $gameName đã được thông báo trước đó.\n";
        }
    }
}

/**
 * Gửi tin nhắn Telegram bằng cURL
 */
function sendTelegramMessage($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        return ["status" => "success", "response" => $response];
    } elseif ($httpCode == 429) {
        return ["status" => "error", "code" => 429, "response" => $response];
    } else {
        return ["status" => "error", "code" => $httpCode, "response" => $response];
    }
}
?>
