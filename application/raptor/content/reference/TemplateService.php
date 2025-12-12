<?php

namespace Raptor\Content;

/**
 * Class TemplateService
 *
 * ReferenceModel моделийн templates content хүснэгтээс keyword-аар орчуулга татах service.
 *
 * Энэ service нь:
 * - reference_templates_content хүснэгтээс keyword-аар орчуулга татна
 * - Language code-г method parameter-оос авна
 * - is_active=1 шалгана
 *
 * @package Raptor\Content
 */
class TemplateService
{
    protected \PDO $pdo;

    /**
     * TemplateService constructor.
     *
     * @param \PDO $pdo Database connection
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Нэг keyword-аар template татах.
     *
     * @param string $code Language code (жишээ: 'mn', 'en')
     * @param string $keyword Template keyword (жишээ: 'tos', 'pp', 'request-new-user')
     * @return array|null Сонгосон хэлний контент эсвэл null
     * 
     * Буцаах утгын бүтэц:
     * [
     *     'title' => string,      // Сонгосон хэлний гарчиг
     *     'content' => string     // Сонгосон хэлний контент
     * ]
     */
    public function getByKeyword(string $code, string $keyword): ?array
    {
        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');
        $reference = $referenceModel->getRowWhere([
            'c.code'      => $code,
            'p.keyword'   => $keyword,
            'p.is_active' => 1
        ]);

        if (empty($reference['localized'][$code])) {
            return null;
        }

        return $reference['localized'][$code];
    }

    /**
     * Олон keyword-аар template-ууд татах.
     *
     * @param string $code Language code (жишээ: 'mn', 'en')
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
    public function getMultipleByKeywords(string $code, array $keywords): array
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
                "c.code='{$code}' " .
                "AND {$keywordWhere} " .
                "AND p.is_active=1"
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['keyword']] = $row['localized'][$code];
        }

        return $result;
    }
}

