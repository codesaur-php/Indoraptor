<?php

namespace Raptor\Content;

/**
 * Class TemplateService
 *
 * Templates хүснэгтээс keyword-аар орчуулга татах service.
 *
 * Энэ service нь:
 * - templates хүснэгтээс keyword-аар орчуулга татана
 * - Language code-г constructor-оос авна
 * - is_active=1 шалгана
 *
 * @package Raptor\Content
 */
class TemplateService
{
    protected \PDO $pdo;
    protected string $code;

    /**
     * TemplateService constructor.
     *
     * @param \PDO $pdo Database connection
     * @param string $code Language code (жишээ: 'mn', 'en')
     */
    public function __construct(\PDO $pdo, string $code)
    {
        $this->pdo = $pdo;
        $this->code = $code;
    }

    /**
     * Нэг keyword-аар template татах.
     *
     * @param string $keyword Template keyword (жишээ: 'tos', 'pp', 'request-new-user')
     * @return array|null Сонгосон хэлний контент эсвэл null
     * 
     * Буцаах утгын бүтэц:
     * [
     *     'title' => string,      // Сонгосон хэлний гарчиг
     *     'content' => string     // Сонгосон хэлний контент
     * ]
     */
    public function getByKeyword(string $keyword): ?array
    {
        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');
        $reference = $referenceModel->getRowWhere([
            'c.code'      => $this->code,
            'p.keyword'   => $keyword,
            'p.is_active' => 1
        ]);

        if (empty($reference['localized'][$this->code])) {
            return null;
        }

        return $reference['localized'][$this->code];
    }

    /**
     * Олон keyword-аар template-ууд татах.
     *
     * @param array $keywords Template keyword-уудын массив (жишээ: ['tos', 'pp'])
     * @return array Keyword => Сонгосон хэлний контент бүтэцтэй массив
     * 
     * Буцаах утгын бүтэц:
     * [
     *     'tos' => [                      // Keyword нь array key болно
     *         'title' => string,          // Сонгосон хэлний гарчиг
     *         'content' => string        // Сонгосон хэлний контент
     *     ],
     *     'pp' => [                       // Keyword нь array key болно
     *         'title' => string,          // Сонгосон хэлний гарчиг
     *         'content' => string        // Сонгосон хэлний контент
     *     ],
     *     // ... бусад keyword-ууд
     * ]
     */
    public function getMultipleByKeywords(array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');
        
        // WHERE нөхцөл үүсгэх
        $keywordConditions = [];
        foreach ($keywords as $keyword) {
            $keywordConditions[] = "p.keyword='{$keyword}'";
        }
        $keywordWhere = '(' . implode(' OR ', $keywordConditions) . ')';
        $rows = $referenceModel->getRows([
            'WHERE' =>
                "c.code='{$this->code}' " .
                "AND {$keywordWhere} " .
                "AND p.is_active=1"
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['keyword']] = $row['localized'][$this->code];
        }

        return $result;
    }
}

