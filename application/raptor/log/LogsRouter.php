<?php

namespace Raptor\Log;

use codesaur\Router\Router;

/**
 * Class LogsRouter
 * 
 * Indoraptor Framework-ийн Log module-ийн маршрут (Router) тодорхойлогч класс.
 * 
 * Энэ класс нь системийн хандалтын протокол (Access Logs)-той холбоотой
 * 3 үндсэн маршрут үүсгэнэ:
 * 
 * ───────────────────────────────────────────────────────────────
 * 1) GET  /dashboard/logs
 *    → Логийн үндсэн жагсаалт харуулах (index)
 * 
 * 2) GET  /dashboard/logs/view
 *    → Нэг логийн дэлгэрэнгүйг modal дотор харуулах (view)
 * 
 * 3) POST /dashboard/logs/retrieve
 *    → AJAX-р логийн листийг шүүх, хайх, ORDER BY, LIMIT хийх API (retrieve)
 * 
 * Эдгээр маршрут нь бүгд LogsController руу холбогдож ажиллана.
 * 
 * @package Raptor\Log
 */
class LogsRouter extends Router
{
    /**
     * LogsRouter constructor.
     * 
     * Маршрутуудыг энд бүртгэнэ.
     * Raptor Framework-ийн Router нь __construct() дотор маршрут бүртгэх зарчмаар ажилладаг.
     */
    public function __construct()
    {
        /**
         * GET /dashboard/logs
         * ─────────────────────────────────────────
         * Логийн үндсэн dashboard хуудсыг харуулна.
         */
        $this->GET('/dashboard/logs', [LogsController::class, 'index'])->name('logs');

        /**
         * GET /dashboard/logs/view
         * ─────────────────────────────────────────
         * Нэг бүртгэлийг modal-аар харах.
         * AJAX modal loader-аар дуудагддаг → logs-view
         */
        $this->GET('/dashboard/logs/view', [LogsController::class, 'view'])->name('logs-view');

        /**
         * POST /dashboard/logs/retrieve
         * ───────────────────────────────────────────────
         * Логийн олон мөр өгөгдлийг серверээс авчрах API.
         * Үйлдлүүд:
         *   - ORDER BY
         *   - LIMIT
         *   - CONTEXT filter (action, alias, user гэх мэт)
         *   - JSON response буцаана.
         * 
         * UI талын JS:
         * fetch('logs-retrieve', { method:'POST', body:{ ... } })
         */
        $this->POST('/dashboard/logs/retrieve', [LogsController::class, 'retrieve'])->name('logs-retrieve');
    }
}
