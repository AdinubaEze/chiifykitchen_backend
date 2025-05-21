<?php

// namespace App\Mail;

// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Mail\Mailable;
// use Illuminate\Queue\SerializesModels;
// use Illuminate\Support\Facades\URL;
// use Illuminate\Support\Facades\Log;

// class EmailVerificationMail extends Mailable implements ShouldQueue
// {
//     use Queueable, SerializesModels;

//     public $user;
//     public $verificationUrl;

//     public function __construct($user)
//     {
//         $this->user = $user;
//         $this->verificationUrl = URL::temporarySignedRoute(
//             'verification.verify',
//             now()->addMinutes(60),
//             ['id' => $user->id, 'hash' => sha1($user->email)]
//         );
//     }

//     public function build()
//     {
//         try {
//             return $this->subject('Verify Your Email Address')
//                 ->markdown('emails.verify-email')
//                 ->onQueue('emails');
                
//         } catch (\Exception $e) {
//             Log::error('Failed to build verification email', [
//                 'user_id' => $this->user->id,
//                 'error' => $e->getMessage()
//             ]);
//             throw $e;
//         }
//     }
// }