<?php

namespace Indoraptor\Mail;

use Throwable;
use Exception;

use PHPMailer\PHPMailer\PHPMailer;

use codesaur\Localization\TranslationModel;

class EmailController extends \Indoraptor\IndoController
{
    
    public function send()
    {
        try {
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                    || !isset($payload['subject'])
                    || !isset($payload['message'])
                    || filter_var($payload['to'], FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('Invalid Request');
            }
            
            $translation = new TranslationModel($this->conn);
            $translation->setTable('dashboard');
            $text = $translation->retrieve($payload['code'] ?? 'en');
            
            if (!getenv('MAIL_SENDER', true)) {
                throw new Exception($text['emailer-not-set'] ?? 'Email sender not found!');
            }
            
            $this->sendBasic(getenv('MAIL_SENDER', true), $payload['to'], $payload['subject'], $payload['message']);
            // TODO: email-succesfully-sent iig translation deer nemeh
            $this->respond(array('success' => array('message' => $text['email-succesfully-sent'] ?? 'Email successfully sent to destination')));
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
    }
    
    public function sendSMTPEmail()
    {
        try {
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                    || !isset($payload['subject'])
                    || !isset($payload['message'])
                    || filter_var($payload['to'], FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('Invalid Request');
            }
        
            $model = new MailerModel($this->conn, array('rbac_accounts', 'id'));
            $rows = $model->getRows();
            $record = end($rows);

            $translation = new TranslationModel($this->conn);
            $translation->setTable('dashboard');
            $text = $translation->retrieve($payload['flag'] ?? 'en');

            if (empty($record) || !isset($record['charset'])
                    || !isset($record['host']) || !isset($record['port'])
                    || !isset($record['email']) || !isset($record['name'])
                    || !isset($record['username']) || !isset($record['password'])
                    || !isset($record['is_smtp']) || !isset($record['smtp_auth']) || !isset($record['smtp_secure'])) {
                throw new Exception($text['emailer-not-set'] ?? 'Email carrier not found!');
            }

            $mailer = new PHPMailer($this->isDevelopment() ? true : null);
            if (((int)$record['is_smtp']) == 1) {
               $mailer->IsSMTP(); 
            }
            $mailer->Mailer = 'smtp';
            $mailer->CharSet = $record['charset'];
            $mailer->SMTPAuth = (bool)((int)$record['smtp_auth']);
            $mailer->SMTPSecure = $record['smtp_secure'];
            $mailer->Host = $record['host'];
            $mailer->Port = $record['port'];
            $mailer->Username = $record['username'];
            $mailer->Password = $record['password'];
            $mailer->SetFrom($record['email'], $record['name']);
            $mailer->AddReplyTo($record['email'], $record['name']);
            $mailer->SMTPOptions = array('ssl' => array(
                'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

            $mailer->MsgHTML($payload['message']);
            $mailer->Subject = $payload['subject'];
            $mailer->AddAddress($payload['to'], $payload['name'] ?? '');
            $mailer->Send();
            
            $this->respond(array('success' => array('message' => $text['email-succesfully-sent'] ?? 'Email successfully sent to destination')));
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
    }
    
    public function sendBasic($from, $to, $subject, $message)
    {
        if (!isset($from)) {
            throw new Exception('Mail sender must be set!');
        } elseif (is_array($from)) {
            $sender = '=?UTF-8?B?' . base64_encode($from[0]) . '?= <' . $from[1] . '>';
        }
  
        if (!isset($to)) {
            throw new Exception('Mail recipient must be set!');
        } elseif (is_array($to)) {
            $recipient = '=?UTF-8?B?' . base64_encode($to[0]) . '?= <' . $to[1] . '>';
        }
        
        if (empty($subject) || empty($message)) {
            throw new Exception('No content? Are u kidding? Mail message must be set!');
        }
        
        $content_type = strpos($message, '</') === false ? 'text/plain' : 'text/html';
        
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        $header  = 'MIME-Version: 1.0' . "\r\n";
        $header .= 'Content-type: ' . $content_type . '; charset=utf-8' . "\r\n";
        $header .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $header .= 'Date: ' . date('r (T)') . "\r\n";
        
        $from_addr =  $from ?? $sender;
        $header .= 'From: ' . $from_addr . "\r\n";
        $header .= 'Reply-To: ' . $from_addr . "\r\n";
        
        $header .= 'X-Mailer: PHP/' . phpversion();
        
        if (!mail($recipient ?? $to, $subject, base64_encode($message), $header)) {
            throw new Exception(error_get_last()['message'] ?? 'Email not sent!');
        }
    }
}
