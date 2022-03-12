<?php

namespace Dizatech\WpQueue;

interface JobInterface{
    public function handle($payload);
}