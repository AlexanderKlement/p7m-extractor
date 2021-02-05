<?php

namespace FilippoToso\P7MExtractor;

use FilippoToso\P7MExtractor\Exceptions\FileNotWritable;
use FilippoToso\P7MExtractor\Exceptions\CouldNotExtractFile;
use FilippoToso\P7MExtractor\Exceptions\P7MNotFound;
use Symfony\Component\Process\Process;

class P7M
{
    protected $source;
    protected $destination;
    protected $binPath;

    public function __construct(string $binPath = NULL)
    {
        $this->binPath = $binPath ?? '/usr/bin/openssl';
    }

    public static function convert(string $source, string $destination, string $binPath = NULL)
    {
        return (new static($binPath))
            ->setSource($source)
            ->setDestination($destination)
            ->save();
    }

    public function save()
    {
        $this->checkSource($this->source);
        $this->checkDestination($this->destination);

        $process = $this->getProcess();
        $process->run();
        if(!$process->isSuccessful())
        {
            $processNew = $this->getProcessNew();
            $processNew->run();
            if(!$process->isSuccessful())
            {
                throw new CouldNotExtractFile($process);
            }
        }
        return TRUE;
    }

    protected function checkSource(string $source)
    {
        if(!is_readable($source))
        {
            throw new P7MNotFound(sprintf('Could not find or read p7m `%s`', $source));
        }
    }

    protected function checkDestination(string $destination)
    {
        if(file_exists($destination) && !is_writable($destination))
        {
            throw new FileNotWritable(sprintf('Could not wrtie file `%s`', $destination));
        }
    }

    protected function getProcess()
    {
        $options = [ $this->binPath, 'smime', '-verify', '-noverify', '-binary', '-in', $this->source, '-inform', 'DER', '-out', $this->destination ];
        return new Process($options);
    }

    /**
     * Added this function to enable extracting some p7m files i was not able otherwise
     */
    protected function getProcessNew()
    {
        $options = [ $this->binPath, 'cms', '-verify', '-noverify', '-in', $this->source, '-inform', 'DER', '-out', $this->destination, '-no_attr_verify' ];
        return new Process($options);
    }

    public function setDestination(string $destination): self
    {
        $this->checkDestination($destination);

        $this->destination = $destination;

        return $this;
    }

    public function setSource(string $source): self
    {
        $this->checkSource($source);

        $this->source = $source;

        return $this;
    }

    public static function extract(string $source, string $binPath = NULL)
    {
        return (new static($binPath))
            ->setSource($source)
            ->get();
    }

    public function get()
    {
        $this->checkSource($this->source);

        $originalDestination = $this->destination;
        $this->destination = $this->getTemporaryFile();

        $process = $this->getProcess();
        $process->run();
        if(!$process->isSuccessful())
        {
            throw new CouldNotExtractFile($process);
        }

        $content = file_get_contents($this->destination);

        $this->destination = $originalDestination;

        return $content;
    }

    protected function getTemporaryFile()
    {
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        return tempnam($tempDir, 'p7m');
    }

}
