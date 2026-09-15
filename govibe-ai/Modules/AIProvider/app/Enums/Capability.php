<?php

namespace Modules\AIProvider\Enums;

/**
 * Capacités qu'un fournisseur peut exposer. Un connecteur implémente
 * uniquement les contrats correspondant à ce qu'il sait faire.
 */
enum Capability: string
{
    case Chat = 'chat';
    case Embeddings = 'embeddings';
    case Images = 'images';
    case Speech = 'speech';
    case Transcription = 'transcription';
    case Vision = 'vision';
    case Ocr = 'ocr';
    case Video = 'video';
}
