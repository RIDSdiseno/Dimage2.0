<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrdenAsignada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $radiologoNombre,
        public readonly int    $ordenId,
        public readonly string $paciente,
        public readonly string $clinica,
        public readonly string $examen,
        public readonly string $prioridad,
    ) {}

    public function envelope(): Envelope
    {
        $asunto = $this->prioridad === 'Urgente'
            ? '🚨 [URGENTE] Nueva orden asignada #' . $this->ordenId . ' — Dimage'
            : 'Nueva orden asignada #' . $this->ordenId . ' — Dimage';

        return new Envelope(subject: $asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orden_asignada');
    }
}
