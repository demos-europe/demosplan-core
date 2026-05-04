<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Class VirusCheckSocket uses direct socket to ClamAV to check for viruses.
 */
class VirusCheckSocket implements VirusCheckInterface
{
    private string $avscanHost;
    private int $avscanPort;
    private int $avscanTimeout;
    private string $noVirusResponse = 'stream: OK';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $this->avscanHost = $this->parameterBag->get('avscan_host');
        $this->avscanPort = $this->parameterBag->get('avscan_port');
        $this->avscanTimeout = $this->parameterBag->get('avscan_timeout');
    }

    public function hasVirus(File $file): bool
    {
        try {
            $this->logger->info('Start virus scan via socket');
            $response = $this->scanFile($file->getRealPath());
            $this->logger->info('Finished virus scan via socket');
            if ($this->noVirusResponse === $response) {
                return false;
            }

            // Only treat as infection when clamd explicitly says FOUND. Empty
            // or unexpected responses indicate a scan error (e.g. clamd hit
            // its 2GB INSTREAM cap or dropped the connection) and must not
            // be misreported as a virus.
            if (!str_contains($response, 'FOUND')) {
                $this->logger->error('clamd returned non-OK/non-FOUND response, treating as scan error', [
                    'response' => $response,
                    'file'     => $file->getFilename(),
                    'size'     => @filesize($file->getRealPath()),
                ]);
                throw new RuntimeException('Virus scan failed: '.($response === '' ? 'empty response from clamd' : $response));
            }

            $this->logger->warning('Virus found', [$response, $file->getRealPath(), $file->getFilename()]);

            return true;
        } catch (Throwable $e) {
            $this->logger->error('Error in virusCheck:', [$e]);
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Scans a file for viruses using ClamAV.
     *
     * @param string $filePath the path to the file to scan
     *
     * @return string The response from ClamAV (e.g. "stream: OK" or "stream: Eicar-Test-Signature FOUND").
     *
     * @throws InvalidArgumentException if the file is not readable
     * @throws RuntimeException         if connection or file reading fails
     */
    public function scanFile(string $filePath): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("File not found or not readable: $filePath");
        }

        // Open a connection to the ClamAV daemon. avscan_timeout is the
        // connect timeout for fsockopen; once connected, PHP otherwise falls
        // back to default_socket_timeout (60s) for reads/writes, which is too
        // short for multi-100MB streams. Apply the parameter to the socket
        // explicitly so large scans don't hit a silent fgets() timeout that
        // looks like an empty clamd response.
        $socket = fsockopen($this->avscanHost, $this->avscanPort, $errno, $errstr, $this->avscanTimeout);
        if (!$socket) {
            throw new RuntimeException("Could not connect to ClamAV daemon: [$errno] $errstr");
        }
        stream_set_timeout($socket, $this->avscanTimeout);

        // Send the zINSTREAM command (note the terminating null byte)
        $command = "zINSTREAM\0";
        fwrite($socket, $command);

        // Open the file and stream its contents in chunks
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            fclose($socket);
            throw new RuntimeException("Failed to open file for reading: $filePath");
        }

        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if (false === $chunk) {
                fclose($socket);
                fclose($handle);
                throw new RuntimeException("Error reading from file: $filePath");
            }
            // Send the chunk length in network (big-endian) order
            $size = pack('N', strlen($chunk));
            $wroteSize = @fwrite($socket, $size);
            $wroteChunk = @fwrite($socket, $chunk);
            // clamd silently closes the socket once StreamMaxLength / its
            // internal 2GB cap is reached, so abort the write loop instead
            // of looping forever on a half-broken pipe.
            if (false === $wroteSize || false === $wroteChunk || $wroteChunk < strlen($chunk)) {
                break;
            }
        }

        // Send a zero-length chunk to mark the end of the stream
        @fwrite($socket, pack('N', 0));

        // Read the response from ClamAV
        $response = '';
        while (!feof($socket)) {
            $data = fgets($socket);
            if (false === $data) {
                break;
            }
            $response .= $data;
            if (str_contains($data, "\n")) {
                break;
            }
        }

        fclose($handle);
        fclose($socket);

        return trim($response);
    }
}
