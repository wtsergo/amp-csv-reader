<?php

namespace Wtsergo\AmpCsvReader;

use Amp\ByteStream\ReadableStream;
use Amp\DeferredFuture;
use Amp\Parser\Parser;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Exception;
use Revolt\EventLoop;
use Traversable;

class CsvReader implements \IteratorAggregate
{
    protected Parser $parser;
    protected ?Queue $queue = null;
    protected DeferredFuture $onComplete;

    public function __construct(
        protected ReadableStream $stream,
        protected int $bufferSize = 0,
        protected $separator = ',',
        protected $enclosure = '"',
        protected $escape = "\\"
    ) {
        $this->onComplete = new DeferredFuture;
        $this->parser = new Parser($this->parse());
    }

    public function __destruct()
    {
        $this->complete();
    }

    private function complete(): void
    {
        if ($this->queue && !$this->queue->isComplete()) {
            $this->queue->complete();
        }

        if (!$this->onComplete->isComplete()) {
            $this->onComplete->complete();
        }
    }

    public function isComplete(): bool
    {
        return $this->queue->isComplete();
    }

    public function onComplete(\Closure $onClose): void
    {
        $this->onComplete->getFuture()->finally($onClose);
    }

    private $buffer = '';
    protected function parse()
    {
        $escapeMode = false;
        while (true) {
            $data = yield "\n";
            $i=0;
            $length = strlen($data);
            while ($i<$length) {
                if ($data[$i] === $this->enclosure) {
                    $escapeMode = !$escapeMode;
                } elseif ($data[$i] === $this->escape) {
                    $i++;
                }
                $i++;
            }
            $this->buffer .= $data;
            if (!$escapeMode) {
                $this->consumeBuffer();
            } else {
                $this->buffer .= "\n";
            }
        }
    }

    private function consumeBuffer()
    {
        if (trim($this->buffer)) {
            $row = str_getcsv($this->buffer, $this->separator, $this->enclosure, $this->escape);
            $this->queue->push($row);
        }
        $this->buffer = '';
    }

    private function read()
    {
        while (null !== ($chunk = $this->stream->read())) {
            $this->parser->push($chunk);
        }
        $this->buffer .= $this->parser->cancel();
        $this->consumeBuffer();
        $this->complete();
    }

    /**
     * @return ConcurrentIterator
     */
    public function getIterator(): Traversable
    {
        if (null === $this->queue) {
            $this->queue = new Queue($this->bufferSize);
            EventLoop::queue($this->read(...));
        }
        return $this->queue->iterate();
    }

}