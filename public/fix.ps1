$bytes = [System.IO.File]::ReadAllBytes('C:\xampp\htdocs\SWAP\public\dashboard.html')
$text = [System.Text.Encoding]::GetEncoding(1256).GetString($bytes)
[System.IO.File]::WriteAllText('C:\xampp\htdocs\SWAP\public\dashboard.html', $text, [System.Text.Encoding]::UTF8)