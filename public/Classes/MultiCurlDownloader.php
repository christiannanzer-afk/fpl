<?php

namespace FPL;

class MultiCurlDownloader
{
    private int $batchSize;
    private int $cacheTime;

    public function __construct(int $batchSize = 50, int $cacheTime = 3600)
    {
        $this->batchSize = $batchSize;
        $this->cacheTime = $cacheTime;
    }

    /**
     * Download multiple URLs in parallel
     * 
     * @param array $urlsAndFiles Format: [key => ['url' => '...', 'filePath' => '...']]
     * @param bool $isBinary For images/binary files
     * @return array Results indexed by key
     */
    public function download(array $urlsAndFiles, bool $isBinary = false): array
    {
        $results = [];
        $chunks = array_chunk($urlsAndFiles, $this->batchSize, true);

        foreach ($chunks as $chunkIndex => $chunk) {
            echo "Verarbeite Batch " . ($chunkIndex + 1) . " von " . count($chunks) . "\n";

            $mh = curl_multi_init();
            curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, $this->batchSize);
            $handles = [];

            foreach ($chunk as $key => $data) {
                $url = $data['url'];
                $filePath = $data['filePath'];

                // Ensure directory exists
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // Check cache
                if ($this->isCacheValid($filePath, $isBinary)) {
                    echo "Lade aus Cache: $filePath\n";
                    $results[$key] = file_get_contents($filePath);
                    continue;
                }

                // Create curl handle
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                
                if ($isBinary) {
                    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                }
                
                curl_multi_add_handle($mh, $ch);
                $handles[$key] = ['ch' => $ch, 'filePath' => $filePath];
            }

            if (empty($handles)) {
                continue; // All from cache
            }

            // Execute multi-curl
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            // Process results
            foreach ($handles as $key => $handleData) {
                $ch = $handleData['ch'];
                $filePath = $handleData['filePath'];

                $response = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($response === false || curl_errno($ch) || $httpCode != 200) {
                    echo "Fehler beim Holen der Daten für $key (HTTP $httpCode): " . curl_error($ch) . "\n";
                    $results[$key] = false;
                } else {
                    if (file_put_contents($filePath, $response) !== false) {
                        echo "Daten erfolgreich geholt und gespeichert: $filePath\n";
                        $results[$key] = $response;
                    } else {
                        echo "Fehler beim Speichern der Datei: $filePath\n";
                        $results[$key] = false;
                    }
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        return $results;
    }

    private function isCacheValid(string $file, bool $permanent = false): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        // Permanent cache (for images/badges)
        if ($permanent || $this->cacheTime === 0) {
            return true;
        }

        return (time() - filemtime($file)) < $this->cacheTime;
    }
}