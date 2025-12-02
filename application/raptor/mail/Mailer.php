<?php

namespace Raptor\Mail;

use GuzzleHttp\Client;
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LogLevel;

use Raptor\Log\Logger;

/**
 * Class Mailer
 * -------------------------------
 * ✉️ Indoraptor Framework-ийн и-мэйл илгээх үндсэн сервис.
 *
 * Энэ класс нь хоёр төрлийн илгээх механизмыг дэмждэг:
 *   1) Brevo (SendInBlue) Transactional Email API — илгээх өндөр найдвартай шийдэл
 *   2) SMTP (PHPMailer) — шаардлагатай үед хэрэглэж болох уламжлалт арга
 *
 * Mailer нь дараах үүргийг гүйцэтгэнэ:
 *   ● .env файлаас Mail тохиргоонуудыг автоматаар уншина
 *   ● From / Reply-To хаягуудыг автомат тохируулна
 *   ● HTML email форматтай мессеж илгээнэ
 *   ● CC / BCC / Attachment дэмжинэ
 *   ● Амжилттай эсвэл алдаатай илгээсэн бүр илгээх үйлдлийг `mailer_log` хүснэгтэд бүртгэнэ
 *
 * Ашиглагдах .env хувьсагчууд:
 * -------------------------------------
 *   INDO_MAIL_FROM               - Илгээгч и-мэйл хаяг (заавал)
 *   INDO_MAIL_FROM_NAME          - Илгээгчийн нэр
 *   INDO_MAIL_REPLY_TO           - Хариу авах и-мэйл
 *   INDO_MAIL_REPLY_TO_NAME      - Хариу авах нэр
 *   INDO_MAIL_BREVO_APIKEY       - Brevo API түлхүүр
 *
 * @package Raptor\Mail
 */
class Mailer extends \codesaur\Http\Client\Mail
{
    use \codesaur\DataObject\PDOTrait;
    
    /**
     * Mailer constructor.
     *
     * @param \PDO      $pdo           Database connection — илгээх протокол лог бичихэд ашиглагдана.
     * @param string|null $from        Илгээгчийн и-мэйл (хоосон бол .env → INDO_MAIL_FROM)
     * @param string|null $fromName    Илгээгчийн нэр (.env → INDO_MAIL_FROM_NAME)
     * @param string|null $replyTo     Хариу хүлээж авах хаяг (.env → INDO_MAIL_REPLY_TO)
     * @param string|null $replyToName Хариу авах нэр (.env → INDO_MAIL_REPLY_TO_NAME)
     *
     * @throws Exception Илгээгчийн хаяг тодорхойгүй бол.
     */
    public function __construct(
        \PDO $pdo,
        ?string $from = null, ?string $fromName = null,
        ?string $replyTo = null, ?string $replyToName = null
    ) {
        $this->setInstance($pdo);
        
        // Илгээгчийг тохируулах
        $this->setFrom(
            $from ?? $_ENV['INDO_MAIL_FROM'] ?? '',
            $fromName ?? $_ENV['INDO_MAIL_FROM_NAME'] ?? ''
        );
        
        // Reply-To (хэрэв өгөгдсөн бол)
        if (!empty($replyTo ?? $_ENV['INDO_MAIL_REPLY_TO'] ?? '')) {
            $this->setReplyTo(
                $replyTo ?? $_ENV['INDO_MAIL_REPLY_TO'],
                $replyToName ?? $_ENV['INDO_MAIL_REPLY_TO_NAME'] ?? ''
            );
        }
    }
    
    /**
     * Email ачаалах тохиргоо (subject, message, recipients, attachments).
     *
     * @param string      $to          Хүлээн авагчийн и-мэйл
     * @param string|null $toName      Хүлээн авагчийн нэр
     * @param string      $subject     Гарчиг
     * @param string      $message     HTML форматтай мессеж
     * @param array|null  $attachments Attachment жагсаалт
     *
     * @return Mailer
     */
    public function mail(
        string $to,
        ?string $toName,
        string $subject,
        string $message,
        ?array $attachments = null
    ): Mailer {
        $this->setSubject($subject);
        $this->setMessage($message);
        $this->targetTo($to, $toName ?? '');
        
        if (\is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $this->addAttachment($attachment);
            }
        }
        
        return $this;
    }
    
    /**
     * И-мэйл илгээх үндсэн функц.
     *
     * Алхамууд:
     * -------------------------
     * 1) .env → INDO_MAIL_BREVO_APIKEY шалгана
     * 2) Brevo API ашиглан transactional e-mail илгээнэ
     * 3) Амжилттай эсвэл алдаа гарсан нөхцөл бүхэнд logger бичнэ
     *
     * @return bool Илгээсэн эсэх
     */
    public function send(): bool
    {
        try {
            if (empty($_ENV['INDO_MAIL_BREVO_APIKEY'] ?? '')) {
                throw new \Exception('Mailer BREVO API KEY not found from environment variables!');
            }

            $result = $this->sendBrevoTransactional($_ENV['INDO_MAIL_BREVO_APIKEY']);
            if (empty($result)) {
                throw new \RuntimeException('Email sending failed!');
            }
            return true;
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            return false;
        } finally {
            $context = ['action' => 'mail-send'];
            $context['To'] = $this->getRecipients('To');
            $cc = $this->getRecipients('Cc');
            if (!empty($cc)) {
                $context['Cc'] = $cc;
            }
            $bcc = $this->getRecipients('Bcc');
            if (!empty($bcc)) {
                $context['Bcc'] = $bcc;
            }
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $context['status'] = 'error';
                $context['code'] = $err->getCode();
                $context['message'] = $err->getMessage();
            } else {
                $level = LogLevel::NOTICE;
                $context['status'] = 'success';
                $context['message'] = 'Email successfully sent to destination';
            }
            $context['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $logger = new Logger($this->pdo);
            $logger->setTable('mailer');
            $toEmail = \reset($context['To'])['email'] ?? '';            
            $mailSubject = $this->subject ?? '';
            $logger->log($level, "[$toEmail] - $mailSubject", $context);
        }
    }
    
    /**
     * SMTP ашиглан илгээх (fallback method).
     *
     * 🔸 Энэ функц нь Brevo API боломжгүй үед хэрэглэгдэнэ.
     *
     * @param string $host         SMTP Host
     * @param int    $port         Порт (25, 465, 587)
     * @param string $username     SMTP хэрэглэгчийн нэр
     * @param string $password     SMTP нууц үг
     * @param string $smtp_secure  ssl|tls
     * @param array  $smtp_options SSL тохиргоо
     *
     * @return bool
     * @throws Exception Attachment URL ашигласан үед
     */
    protected function sendSMTP(
        $host, $port, $username, $password, 
        $smtp_secure = 'ssl', 
        $smtp_options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]
    ): bool {   
        $this->assertValues();

        $phpMailer = new PHPMailer(CODESAUR_DEVELOPMENT ? true : null);
        $phpMailer->isSMTP();
        $phpMailer->CharSet = 'UTF-8';
        $phpMailer->SMTPAuth = true;
        $phpMailer->SMTPSecure = $smtp_secure;
        $phpMailer->Host = $host;
        $phpMailer->Port = $port;
        $phpMailer->Username = $username;
        $phpMailer->Password = $password;
        $phpMailer->setFrom($this->from, $this->fromName);
        if (empty($this->replyTo)) {
            $replyTo = $this->from;
            $replyToName = $this->fromName;
        } else {
            $replyTo = $this->replyTo;
            $replyToName = $this->replyToName;
        }
        $phpMailer->addReplyTo($replyTo, $replyToName);
        $phpMailer->SMTPOptions = $smtp_options;
        $phpMailer->msgHTML($this->message);
        $phpMailer->Subject = $this->subject;
        foreach ($this->getRecipients('To') as $to) {
            $phpMailer->addAddress($to['email'], $to['name'] ?? '');
        }
        foreach ($this->getRecipients('Cc') as $cc) {
            $phpMailer->addCC($cc['email'], $cc['name'] ?? '');
        }
        foreach ($this->getRecipients('Bcc') as $bcc) {
            $phpMailer->addBCC($bcc['email'], $bcc['name'] ?? '');
        }

        foreach ($this->getAttachments() as $attachment) {
            if (isset($attachment['path'])) {
                $phpMailer->addAttachment($attachment['path'], $attachment['name']);
            } elseif (isset($attachment['url'])) {
                throw new \Exception('Explicitly *does not* support passing URLs; PHPMailer is not an HTTP client.');
            } elseif (isset($attachment['content'])) {
                $phpMailer->addStringAttachment($attachment['content'], $attachment['name']);
            }
        }

        return $phpMailer->send();
    }
    
    /**
     * Brevo Transactional Email API ашиглаж илгээх.
     *
     * @param string $apiKey Brevo API key (.env → INDO_MAIL_BREVO_APIKEY)
     *
     * @return array API response
     * @throws Exception Brevo local file attachment дэмждэггүй
     */
    protected function sendBrevoTransactional($apiKey): array
    {
        $this->assertValues();
        
        $credentials = Configuration::getDefaultConfiguration()->setApiKey(
            'api-key', $apiKey // your Brevo API key
        );
        $apiInstance = new TransactionalEmailsApi(new Client(), $credentials);
        $options = [
            'subject' => $this->subject,
            'htmlContent' => $this->message,
            'to' => $this->getRecipients('To')
        ];
        $cc = $this->getRecipients('Cc');
        if (!empty($cc)) {
            $options['cc'] = $cc;
        }
        $bcc = $this->getRecipients('Bcc');
        if (!empty($bcc)) {
            $options['bcc'] = $bcc;
        }        
        if (!empty($this->fromName)) {
            $options['sender'] = ['name' => $this->fromName, 'email' => $this->from];
        } else {
            $options['sender'] = ['email' => $this->from];
        }
        if (!empty($this->replyTo)) {
            if (!empty($this->replyToName)) {
                $options['replyTo'] = ['name' => $this->replyToName, 'email' => $this->replyTo];
            } else {
                $options['replyTo'] = ['email' => $this->replyTo];
            }
        }
        
        $attachments = [];
        foreach ($this->getAttachments() as $attachment) {
            if (isset($attachment['path'])) {
                throw new \Exception("Brevo's SendSmtpEmail doesn't support local file!");
            } elseif (isset($attachment['url'])) {
                $attachments[] = ['url' => $attachment['url'], 'name' => $attachment['name']];
            } elseif (isset($attachment['content'])) {
                $attachments[] = ['content' => $attachment['content'], 'name' => $attachment['name']];
            }
        }
        if (!empty($attachments)) {
            $options['attachment'] = $attachments;
        }
        
        $sendSmtpEmail = new SendSmtpEmail($options);
        return (array) $apiInstance->sendTransacEmail($sendSmtpEmail);
    }
}
