# KnuckleLog

Standalone PHP fast & low memory usage class for Nginx access log parsing.

This class is designed to parsing small or large NGINX access log files.

Its feature is that it handles part of the file with a row limit, and it also returns the total number of rows in the file.

Tests on access.log => 500 MB.

Docs coming soon...

## Usage

```php

// Initialize (parse nginx accesss log in default format with offset = 0 and limit 10 lines 
$data = new KnuckleLog('/var/log/nginx/access.log', '%h %l %u %t "%r" %>s %O "%{Referer}i" \"%{User-Agent}i"', 0, 10);

// Get array of data & data count
$array = $data->worker();

// Total lines in log file
echo '<h1>'.$array['totalLines'].'</h1>';

// Dump data array
echo '<pre>';
print_r($array['data']);
echo '</pre>';

```
