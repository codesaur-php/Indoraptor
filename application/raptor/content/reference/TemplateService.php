<?php

namespace Raptor\Content;

/**
 * Class TemplateService
 *
 * LocalizedModel-ийн templates content хүснэгтээс keyword-аар орчуулга татах service.
 *
 * Энэ service нь:
 * - reference_templates_content хүснэгтээс keyword-аар орчуулга татна
 * - Language code-г method parameter-оос авна
 * - is_active=1 шалгана
 * - LocalizedModel-ийн шинэ method-уудыг ашиглана
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
     * @param string $keyword Template keyword (жишээ: 'tos', 'pp', 'request-new-user')
     * @param string $code Language code (жишээ: 'mn', 'en')
     * @return array|null Сонгосон хэлний контент эсвэл null
     * 
     * Буцаах утгын бүтэц:
     * [
     *     'title' => string,      // Сонгосон хэлний гарчиг
     *     'content' => string     // Сонгосон хэлний контент
     * ]
     */
    public function getByKeyword(string $keyword, string $code): ?array
    {
        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');
        
        // LocalizedModel-ийн getRowWhere() method ашиглах
        $reference = $referenceModel->getRowWhere([
            'c.code'      => $code,
            'p.keyword'   => $keyword,
            'p.is_active' => 1
        ]);

        if (empty($reference) || empty($reference['localized'][$code])) {
            return null;
        }

        return $reference['localized'][$code];
    }

    /**
     * Олон keyword-аар template-ууд татах.
     *
     * @param array $keywords Template keyword-уудын массив (жишээ: ['tos', 'pp'])
     * @param string $code Language code (жишээ: 'mn', 'en')
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
    public function getByKeywords(array $keywords, string $code): array
    {
        if (empty($keywords)) {
            return [];
        }

        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');
        
        // LocalizedModel-ийн getRows() method ашиглах
        // WHERE нөхцөл үүсгэх - keyword-уудыг IN clause ашиглах
        $placeholders = [];
        $params = [];
        foreach ($keywords as $index => $keyword) {
            $placeholder = ":keyword_$index";
            $placeholders[] = $placeholder;
            $params[$placeholder] = $keyword;
        }
        
        $keywordIn = 'p.keyword IN (' . implode(', ', $placeholders) . ')';
        $params[':code'] = $code;
        
        $rows = $referenceModel->getRows([
            'WHERE' => "c.code=:code AND $keywordIn AND p.is_active=1",
            'PARAM' => $params
        ]);

        $result = [];
        foreach ($rows as $row) {
            // Keyword-г primary талбараас авах
            $keyword = $row['keyword'] ?? null;
            if ($keyword && isset($row['localized'][$code])) {
                $result[$keyword] = $row['localized'][$code];
            }
        }

        return $result;
    }
}

