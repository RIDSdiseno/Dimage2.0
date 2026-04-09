<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertaRadiologo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $radiologoNombre,
        public readonly array  $ordenes,   // [['id','paciente','enviada','dias']]
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Órdenes pendientes de informe — Dimage',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alerta_radiologo',
        );
    }
}
