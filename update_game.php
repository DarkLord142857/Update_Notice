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

// Tìm dữ liệu trong bảng
$xpath = new DOMXPath($dom);
$rows = $xpath->query("//tr");

// Đọc dữ liệu từ file JSON (nếu có)
$existingData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $existingData = json_decode($jsonContent, true);

    // Nếu JSON không hợp lệ, đặt giá trị mặc định là mảng rỗng
    if (!is_array($existingData)) {
        $existingData = [];
    }
}

// Lấy danh sách ID đã lưu
$existingIds = array_column($existingData, 'id');


// Duyệt từng hàng trong bảng
foreach ($rows as $row) {
    if ($row instanceof DOMElement) {
        $columns = $row->getElementsByTagName("td");
    } else {
        die("Lỗi: \$row không phải là DOMElement");
    }
    
    if ($columns->length >= 5) {
        $id = trim($columns->item(0)->textContent);
        $gameName = trim($columns->item(1)->textContent);
        $size = trim($columns->item(2)->textContent);
        $date = trim($columns->item(3)->textContent);
        $status = trim($columns->item(4)->textContent);


        // Kiểm tra xem thông báo này đã được gửi trước đó chưa
        $found = false;
        foreach ($existingData as $entry) {
            if ($entry['id'] == $id && $entry['date'] == $date) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Gửi tin nhắn Telegram
            $message = "🎮 Game mới cập nhật:\n";
            $message .= "🆔 ID: $id\n";
            $message .= "🎮 Tên: $gameName\n";
            $message .= "📂 Kích thước: $size\n";
            $message .= "📅 Ngày cập nhật: $date\n";

            $telegramApi = "https://api.telegram.org/bot$botToken/sendMessage";
            $dataTelegram = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ];

            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($dataTelegram)
                ]
            ];

            $context  = stream_context_create($options);
            $result = file_get_contents($telegramApi, false, $context);

            if ($result) {
                echo "Đã gửi thông báo cho $gameName\n";
                
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
            } else {
                echo "Gửi tin nhắn thất bại!\n";
            }
        } else {
            echo "Game $gameName đã được thông báo trước đó.\n";
        }
    }
}
?>
