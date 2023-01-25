<?php

namespace Indoraptor\Localization;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class CountriesModel extends MultiModel
{
    function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns([
           (new Column('id', 'varchar', 19))->primary()->unique()->notNull(),
            new Column('speak', 'varchar', 64),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setContentColumns([new Column('title', 'varchar', 255)]);
        
        $this->setTable('localization_countries', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    public function retrieve(?string $code = null): array
    {        
        $countries = [];
        $codeName = $this->getCodeColumn()->getName();
        if (empty($code)) {
            $stmt = $this->select(
                "p.id as id, c.$codeName as $codeName, c.title as title",
                ['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.id']);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $countries[$row['id']][$row[$codeName]] = $row['title'];
            }
        } else {
            $code = preg_replace('/[^A-Za-z]/', '', $code);
            $condition = [
                'WHERE' => "c.$codeName=:1 AND p.is_active=1",
                'ORDER BY' => 'p.id',
                'PARAM' => [':1' => $code]
            ];
            $stmt = $this->select("p.id as id, c.$codeName as $codeName, c.title as title", $condition);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $countries[$row['id']] = $row['title'];
            }
        }
        return $countries;
    }
    
    function __initial()
    {
        parent::__initial();
        
        $table = $this->getName();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $this->insert(['id' => 'AD', 'speak' => 'Català'], ['mn' => ['title' => 'Андорра'], 'en' => ['title' => 'Andorra']]);
        $this->insert(['id' => 'AE', 'speak' => 'الإمارات العربية المتحدة'], ['en' => ['title' => 'United Arab Emirates'], 'mn' => ['title' => 'Арабын Нэгдсэн Эмират']]);
        $this->insert(['id' => 'AF', 'speak' => 'د افغانستان اسلامي جمهوریت'], ['en' => ['title' => 'Afghanistan'], 'mn' => ['title' => 'Афганистан']]);
        $this->insert(['id' => 'AI', 'speak' => 'English'], ['mn' => ['title' => 'Anguilla'], 'en' => ['title' => 'Anguilla']]);
        $this->insert(['id' => 'AL', 'speak' => 'Shqip'], ['en' => ['title' => 'Albania'], 'mn' => ['title' => 'Албани']]);
        $this->insert(['id' => 'AM', 'speak' => 'Armenian'], ['en' => ['title' => 'Armenia'], 'mn' => ['title' => 'Армен']]);
        $this->insert(['id' => 'AN', 'speak' => 'Curaçao'], ['mn' => ['title' => 'Нидерландын Антилийн арлууд'], 'en' => ['title' => 'Netherlands Antilles']]);
        $this->insert(['id' => 'AO', 'speak' => 'Portuguese'], ['mn' => ['title' => 'Ангол'], 'en' => ['title' => 'Angola']]);
        $this->insert(['id' => 'AQ', 'speak' => 'English'], ['en' => ['title' => 'Antarctica'], 'mn' => ['title' => 'Антарктик']]);
        $this->insert(['id' => 'AR', 'speak' => 'Spanish'], ['mn' => ['title' => 'Аргентин'], 'en' => ['title' => 'Argentina']]);
        $this->insert(['id' => 'AS', 'speak' => 'Sāmoa'], ['mn' => ['title' => 'American Samoa'], 'en' => ['title' => 'American Samoa']]);
        $this->insert(['id' => 'AT', 'speak' => 'Deutsch'], ['mn' => ['title' => 'Австри'], 'en' => ['title' => 'Austria']]);
        $this->insert(['id' => 'AU', 'speak' => 'English'], ['mn' => ['title' => 'Автрали'], 'en' => ['title' => 'Australia']]);
        $this->insert(['id' => 'AW', 'speak' => 'Dutch'], ['mn' => ['title' => 'Аруба'], 'en' => ['title' => 'Aruba']]);
        $this->insert(['id' => 'AZ', 'speak' => 'Azerbaijani'], ['en' => ['title' => 'Azerbaijan'], 'mn' => ['title' => 'Азербайжан']]);
        $this->insert(['id' => 'BA', 'speak' => 'Bosnian, Croatian and Serbian '], ['mn' => ['title' => 'Босни Херцеговин'], 'en' => ['title' => 'Bosnia and Herzegowina']]);
        $this->insert(['id' => 'BB', 'speak' => 'English'], ['mn' => ['title' => 'Барбадос'], 'en' => ['title' => 'Barbados']]);
        $this->insert(['id' => 'BD', 'speak' => 'Bengali'], ['en' => ['title' => 'Bangladesh'], 'mn' => ['title' => 'Бангладеш']]);
        $this->insert(['id' => 'BE', 'speak' => 'French, Dutch, German'], ['en' => ['title' => 'Belgium'], 'mn' => ['title' => 'Бельги']]);
        $this->insert(['id' => 'BF', 'speak' => 'Burkina Faso'], ['mn' => ['title' => 'Буркина Фасо'], 'en' => ['title' => 'Burkina Faso']]);
        $this->insert(['id' => 'BG', 'speak' => 'Bulgarian'], ['en' => ['title' => 'Bulgaria'], 'mn' => ['title' => 'Болгар']]);
        $this->insert(['id' => 'BH', 'speak' => 'Arabic'], ['en' => ['title' => 'Bahrain'], 'mn' => ['title' => 'Бахрейн']]);
        $this->insert(['id' => 'BI', 'speak' => 'French, Kirund'], ['mn' => ['title' => 'Бурунди'], 'en' => ['title' => 'Burundi']]);
        $this->insert(['id' => 'BJ', 'speak' => 'French'], ['mn' => ['title' => 'Бенин'], 'en' => ['title' => 'Benin']]);
        $this->insert(['id' => 'BM', 'speak' => 'Bermud üçbucağ'], ['en' => ['title' => 'Bermuda'], 'mn' => ['title' => 'Бермуд']]);
        $this->insert(['id' => 'BN', 'speak' => 'Malay'], ['mn' => ['title' => 'Бруней'], 'en' => ['title' => 'Brunei Darussalam']]);
        $this->insert(['id' => 'BO', 'speak' => 'Spanish, Aymara, Chiquitano'], ['mn' => ['title' => 'Болив'], 'en' => ['title' => 'Bolivia']]);
        $this->insert(['id' => 'BR', 'speak' => 'Portuguese'], ['mn' => ['title' => 'Бразил'], 'en' => ['title' => 'Brazil']]);
        $this->insert(['id' => 'BS', 'speak' => 'English'], ['mn' => ['title' => 'Бахам'], 'en' => ['title' => 'Bahamas']]);
        $this->insert(['id' => 'BT', 'speak' => 'Dzongkha'], ['mn' => ['title' => 'Бутан'], 'en' => ['title' => 'Bhutan']]);
        $this->insert(['id' => 'BV', 'speak' => 'Norwegia'], ['en' => ['title' => 'Bouvet Island'], 'mn' => ['title' => 'Bouvet Island']]);
        $this->insert(['id' => 'BW', 'speak' => 'English, Tswana'], ['mn' => ['title' => 'Ботсван'], 'en' => ['title' => 'Botswana']]);
        $this->insert(['id' => 'BY', 'speak' => 'Belarusian'], ['mn' => ['title' => 'Беларусь'], 'en' => ['title' => 'Belarus']]);
        $this->insert(['id' => 'BZ', 'speak' => 'English'], ['en' => ['title' => 'Belize'], 'mn' => ['title' => 'Белиз']]);
        $this->insert(['id' => 'CA', 'speak' => 'English'], ['en' => ['title' => 'Canada'], 'mn' => ['title' => 'Канад']]);
        $this->insert(['id' => 'CC', 'speak' => 'English'], ['mn' => ['title' => 'Cocos (Keeling) Islands'], 'en' => ['title' => 'Cocos (Keeling) Islands']]);
        $this->insert(['id' => 'CD', 'speak' => 'French'], ['en' => ['title' => 'Congo, the Democratic Republic of the'], 'mn' => ['title' => 'Бүгд Найрамдах Ардчилсан Конго']]);
        $this->insert(['id' => 'CF', 'speak' => 'Sango, French'], ['en' => ['title' => 'Central African Republic'], 'mn' => ['title' => 'Төв Африк']]);
        $this->insert(['id' => 'CG', 'speak' => 'French'], ['mn' => ['title' => 'Конго'], 'en' => ['title' => 'Congo']]);
        $this->insert(['id' => 'CH', 'speak' => 'French, German, Italian, Romans'], ['mn' => ['title' => 'Швейцарь'], 'en' => ['title' => 'Switzerland']]);
        $this->insert(['id' => 'CI', 'speak' => 'French'], ['en' => ['title' => 'Cote d\'Ivoire'], 'mn' => ['title' => 'Кот Дивуар']]);
        $this->insert(['id' => 'CK', 'speak' => 'English, Rarotongan'], ['mn' => ['title' => 'Cook Islands'], 'en' => ['title' => 'Cook Islands']]);
        $this->insert(['id' => 'CL', 'speak' => 'Spanish'], ['mn' => ['title' => 'Чили'], 'en' => ['title' => 'Chile']]);
        $this->insert(['id' => 'CM', 'speak' => 'French, English'], ['mn' => ['title' => 'Камерун'], 'en' => ['title' => 'Cameroon']]);
        $this->insert(['id' => 'CN', 'speak' => '中文'], ['mn' => ['title' => 'Хятад'], 'en' => ['title' => 'China']]);
        $this->insert(['id' => 'CO', 'speak' => 'Spanish'], ['mn' => ['title' => 'Колумб'], 'en' => ['title' => 'Colombia']]);
        $this->insert(['id' => 'CR', 'speak' => 'Spanish'], ['en' => ['title' => 'Costa Rica'], 'mn' => ['title' => 'Коста рика']]);
        $this->insert(['id' => 'CU', 'speak' => 'Spanish'], ['mn' => ['title' => 'Куба'], 'en' => ['title' => 'Cuba']]);
        $this->insert(['id' => 'CV', 'speak' => 'Portuguese'], ['mn' => ['title' => 'Кабо Верде'], 'en' => ['title' => 'Cape Verde']]);
        $this->insert(['id' => 'CX', 'speak' => 'English'], ['en' => ['title' => 'Christmas Island'], 'mn' => ['title' => 'Christmas Island']]);
        $this->insert(['id' => 'CY', 'speak' => 'Turkish, Greek'], ['en' => ['title' => 'Cyprus'], 'mn' => ['title' => 'Кибр']]);
        $this->insert(['id' => 'CZ', 'speak' => 'Čeština'], ['mn' => ['title' => 'Чех'], 'en' => ['title' => 'Czech Republic']]);
        $this->insert(['id' => 'DE', 'speak' => 'Deutsch'], ['en' => ['title' => 'Germany'], 'mn' => ['title' => 'Герман']]);
        $this->insert(['id' => 'DJ', 'speak' => 'French, Arabic'], ['mn' => ['title' => 'Djibouti'], 'en' => ['title' => 'Djibouti']]);
        $this->insert(['id' => 'DK', 'speak' => 'Danish'], ['mn' => ['title' => 'Дани'], 'en' => ['title' => 'Denmark']]);
        $this->insert(['id' => 'DM', 'speak' => 'English'], ['mn' => ['title' => 'Доминик'], 'en' => ['title' => 'Dominica']]);
        $this->insert(['id' => 'DO', 'speak' => 'Spanish'], ['mn' => ['title' => 'Доминикан'], 'en' => ['title' => 'Dominican Republic']]);
        $this->insert(['id' => 'DZ', 'speak' => 'Arabic, Berber'], ['mn' => ['title' => 'Алжир'], 'en' => ['title' => 'Algeria']]);
        $this->insert(['id' => 'EC', 'speak' => 'Spanish'], ['mn' => ['title' => 'Эквадор'], 'en' => ['title' => 'Ecuador']]);
        $this->insert(['id' => 'EE', 'speak' => 'Estonian'], ['mn' => ['title' => 'Эстони'], 'en' => ['title' => 'Estonia']]);
        $this->insert(['id' => 'EG', 'speak' => 'Arabic'], ['mn' => ['title' => 'Египет'], 'en' => ['title' => 'Egypt']]);
        $this->insert(['id' => 'EH', 'speak' => 'Arabic'], ['mn' => ['title' => 'Баруун Сахар'], 'en' => ['title' => 'Western Sahara']]);
        $this->insert(['id' => 'ER', 'speak' => ''], ['en' => ['title' => 'Eritrea'], 'mn' => ['title' => 'Эквадор']]);
        $this->insert(['id' => 'ES', 'speak' => 'España'], ['en' => ['title' => 'Spain'], 'mn' => ['title' => 'Испани']]);
        $this->insert(['id' => 'ET', 'speak' => ''], ['mn' => ['title' => 'Этиоп'], 'en' => ['title' => 'Ethiopia']]);
        $this->insert(['id' => 'FI', 'speak' => 'Finnish '], ['en' => ['title' => 'Finland'], 'mn' => ['title' => 'Финланд']]);
        $this->insert(['id' => 'FJ', 'speak' => 'English, Fijian'], ['en' => ['title' => 'Fiji'], 'mn' => ['title' => 'Фижи']]);
        $this->insert(['id' => 'FK', 'speak' => 'English'], ['mn' => ['title' => 'Falkland Islands (Malvinas)'], 'en' => ['title' => 'Falkland Islands (Malvinas)']]);
        $this->insert(['id' => 'FM', 'speak' => 'English'], ['en' => ['title' => 'Micronesia, Federated States of'], 'mn' => ['title' => 'Микронез']]);
        $this->insert(['id' => 'FO', 'speak' => 'Danish, Faroese'], ['mn' => ['title' => 'Faroe Islands'], 'en' => ['title' => 'Faroe Islands']]);
        $this->insert(['id' => 'FR', 'speak' => 'French'], ['mn' => ['title' => 'Франц'], 'en' => ['title' => 'France']]);
        $this->insert(['id' => 'GA', 'speak' => 'French, ɡabɔ̃'], ['mn' => ['title' => 'Габон'], 'en' => ['title' => 'Gabon']]);
        $this->insert(['id' => 'GB', 'speak' => 'British English'], ['mn' => ['title' => 'Их Британи'], 'en' => ['title' => 'United Kingdom']]);
        $this->insert(['id' => 'GD', 'speak' => 'English'], ['mn' => ['title' => 'Гренада'], 'en' => ['title' => 'Grenada']]);
        $this->insert(['id' => 'GE', 'speak' => 'Georgian: საქართველო'], ['mn' => ['title' => 'Гүрж'], 'en' => ['title' => 'Georgia']]);
        $this->insert(['id' => 'GF', 'speak' => 'Guyane française'], ['mn' => ['title' => 'Францын Гвиней'], 'en' => ['title' => 'French Guiana']]);
        $this->insert(['id' => 'GH', 'speak' => 'English'], ['mn' => ['title' => 'Гана'], 'en' => ['title' => 'Ghana']]);
        $this->insert(['id' => 'GI', 'speak' => 'English'], ['mn' => ['title' => 'Гибралтар'], 'en' => ['title' => 'Gibraltar']]);
        $this->insert(['id' => 'GL', 'speak' => 'Greenlandic'], ['en' => ['title' => 'Greenland'], 'mn' => ['title' => 'Гренланд']]);
        $this->insert(['id' => 'GM', 'speak' => 'English'], ['en' => ['title' => 'Gambia'], 'mn' => ['title' => 'Гамби']]);
        $this->insert(['id' => 'GN', 'speak' => 'French'], ['mn' => ['title' => 'Гвиней'], 'en' => ['title' => 'Guinea']]);
        $this->insert(['id' => 'GP', 'speak' => 'French'], ['en' => ['title' => 'Guadeloupe'], 'mn' => ['title' => 'Guadeloupe']]);
        $this->insert(['id' => 'GQ', 'speak' => ''], ['en' => ['title' => 'Equatorial Guinea'], 'mn' => ['title' => 'Экваторын Гвиней']]);
        $this->insert(['id' => 'GR', 'speak' => 'Greek: Ελλάδα'], ['mn' => ['title' => 'Грек'], 'en' => ['title' => 'Greece']]);
        $this->insert(['id' => 'GS', 'speak' => 'English'], ['en' => ['title' => 'South Georgia and the South Sandwich Islands'], 'mn' => ['title' => 'South Georgia and the South Sandwich Islands']]);
        $this->insert(['id' => 'GT', 'speak' => ''], ['mn' => ['title' => 'Guatemala'], 'en' => ['title' => 'Guatemala']]);
        $this->insert(['id' => 'GU', 'speak' => 'Portuguese'], ['mn' => ['title' => 'Гуам'], 'en' => ['title' => 'Guam']]);
        $this->insert(['id' => 'GW', 'speak' => ''], ['mn' => ['title' => 'Guinea-Bissau'], 'en' => ['title' => 'Guinea-Bissau']]);
        $this->insert(['id' => 'GY', 'speak' => 'English'], ['mn' => ['title' => 'Гайана'], 'en' => ['title' => 'Guyana']]);
        $this->insert(['id' => 'HK', 'speak' => 'English, Chinese'], ['mn' => ['title' => 'Хонг Гонг'], 'en' => ['title' => 'Hong Kong']]);
        $this->insert(['id' => 'HM', 'speak' => 'Australia'], ['mn' => ['title' => 'Мк Доналдын арлууд'], 'en' => ['title' => 'Heard and Mc Donald Islands']]);
        $this->insert(['id' => 'HN', 'speak' => 'Spanish'], ['mn' => ['title' => 'Гондурас'], 'en' => ['title' => 'Honduras']]);
        $this->insert(['id' => 'HR', 'speak' => 'Hrvatska'], ['mn' => ['title' => 'Хорват'], 'en' => ['title' => 'Croatia (Hrvatska)']]);
        $this->insert(['id' => 'HT', 'speak' => 'French Haitian Creole'], ['mn' => ['title' => 'Гайти'], 'en' => ['title' => 'Haiti']]);
        $this->insert(['id' => 'HU', 'speak' => 'Hungarian: Magyarország'], ['en' => ['title' => 'Hungary'], 'mn' => ['title' => 'Унгари']]);
        $this->insert(['id' => 'ID', 'speak' => 'Indonesian'], ['en' => ['title' => 'Indonesia'], 'mn' => ['title' => 'Индонези']]);
        $this->insert(['id' => 'IE', 'speak' => 'English, Irish, Ulster Scots'], ['mn' => ['title' => 'Ирланд'], 'en' => ['title' => 'Ireland']]);
        $this->insert(['id' => 'IL', 'speak' => 'Hebrew, Arabic'], ['en' => ['title' => 'Israel'], 'mn' => ['title' => 'Израйл']]);
        $this->insert(['id' => 'IN', 'speak' => 'Hindi, English'], ['en' => ['title' => 'India'], 'mn' => ['title' => 'Энэтхэг']]);
        $this->insert(['id' => 'IO', 'speak' => 'English'], ['mn' => ['title' => 'Их Британийн Энэтхэгийн далайн нутаг'], 'en' => ['title' => 'British Indian Ocean Territory']]);
        $this->insert(['id' => 'IQ', 'speak' => 'Arabic Kurdish'], ['en' => ['title' => 'Iraq'], 'mn' => ['title' => 'Ирак']]);
        $this->insert(['id' => 'IR', 'speak' => 'Persian'], ['mn' => ['title' => 'Иран'], 'en' => ['title' => 'Iran (Islamic Republic of)']]);
        $this->insert(['id' => 'IS', 'speak' => 'Icelandic'], ['mn' => ['title' => 'Исланд'], 'en' => ['title' => 'Iceland']]);
        $this->insert(['id' => 'IT', 'speak' => 'Italiana'], ['mn' => ['title' => 'Итали'], 'en' => ['title' => 'Italy']]);
        $this->insert(['id' => 'JM', 'speak' => 'English'], ['mn' => ['title' => 'Ямайка'], 'en' => ['title' => 'Jamaica']]);
        $this->insert(['id' => 'JO', 'speak' => 'Arabic: الأردن'], ['mn' => ['title' => 'Иордан'], 'en' => ['title' => 'Jordan']]);
        $this->insert(['id' => 'JP', 'speak' => ''], ['mn' => ['title' => 'Япон'], 'en' => ['title' => 'Japan']]);
        $this->insert(['id' => 'KE', 'speak' => 'Swahili, English'], ['mn' => ['title' => 'Кень'], 'en' => ['title' => 'Kenya']]);
        $this->insert(['id' => 'KG', 'speak' => 'Kyrgyz'], ['en' => ['title' => 'Kyrgyzstan'], 'mn' => ['title' => 'Киргестан']]);
        $this->insert(['id' => 'KH', 'speak' => 'Khmer'], ['mn' => ['title' => 'Камбож'], 'en' => ['title' => 'Cambodia']]);
        $this->insert(['id' => 'KI', 'speak' => 'English Gilbertese'], ['en' => ['title' => 'Kiribati'], 'mn' => ['title' => 'Кирибати']]);
        $this->insert(['id' => 'KM', 'speak' => 'Comorian, Arabic, French'], ['en' => ['title' => 'Comoros'], 'mn' => ['title' => 'Коморын арал']]);
        $this->insert(['id' => 'KN', 'speak' => 'English'], ['mn' => ['title' => 'Сент Китс Невисийн Холбооны'], 'en' => ['title' => 'Saint Kitts and Nevis']]);
        $this->insert(['id' => 'KP', 'speak' => '조선말'], ['en' => ['title' => 'Korea, Democratic People\'s Republic of'], 'mn' => ['title' => 'Умард Солонгос']]);
        $this->insert(['id' => 'KR', 'speak' => '한국어'], ['en' => ['title' => 'Korea, Republic of'], 'mn' => ['title' => 'Өмнөд Солонгос']]);
        $this->insert(['id' => 'KW', 'speak' => 'Arabic'], ['mn' => ['title' => 'Кувейт'], 'en' => ['title' => 'Kuwait']]);
        $this->insert(['id' => 'KY', 'speak' => 'English'], ['en' => ['title' => 'Cayman Islands'], 'mn' => ['title' => 'Кайманы арлууд']]);
        $this->insert(['id' => 'KZ', 'speak' => '‎Kazakh'], ['mn' => ['title' => 'Казакстан'], 'en' => ['title' => 'Kazakhstan']]);
        $this->insert(['id' => 'LA', 'speak' => 'Lao'], ['mn' => ['title' => 'Лаос'], 'en' => ['title' => 'Lao People\'s Democratic Republic']]);
        $this->insert(['id' => 'LB', 'speak' => 'Arabic'], ['mn' => ['title' => 'Ливан'], 'en' => ['title' => 'Lebanon']]);
        $this->insert(['id' => 'LC', 'speak' => 'English'], ['mn' => ['title' => 'Сент-Люси'], 'en' => ['title' => 'Saint LUCIA']]);
        $this->insert(['id' => 'LI', 'speak' => 'German'], ['mn' => ['title' => 'Лихтенштейн'], 'en' => ['title' => 'Liechtenstein']]);
        $this->insert(['id' => 'LK', 'speak' => 'Sinhala, Tamil, English'], ['mn' => ['title' => 'Шри Ланка'], 'en' => ['title' => 'Sri Lanka']]);
        $this->insert(['id' => 'LR', 'speak' => 'English'], ['mn' => ['title' => 'Ливери'], 'en' => ['title' => 'Liberia']]);
        $this->insert(['id' => 'LS', 'speak' => 'English, Southern Sotho'], ['en' => ['title' => 'Lesotho'], 'mn' => ['title' => 'Лесото']]);
        $this->insert(['id' => 'LT', 'speak' => '‎Lithuanian'], ['mn' => ['title' => 'Литва'], 'en' => ['title' => 'Lithuania']]);
        $this->insert(['id' => 'LU', 'speak' => 'German, French, Luxembourgish'], ['en' => ['title' => 'Luxembourg'], 'mn' => ['title' => 'Люксембург']]);
        $this->insert(['id' => 'LV', 'speak' => '‎Latvian'], ['en' => ['title' => 'Latvia'], 'mn' => ['title' => 'Латви']]);
        $this->insert(['id' => 'LY', 'speak' => 'Arabic'], ['mn' => ['title' => 'Ливи'], 'en' => ['title' => 'Libyan Arab Jamahiriya']]);
        $this->insert(['id' => 'MA', 'speak' => 'Arabic'], ['en' => ['title' => 'Morocco'], 'mn' => ['title' => 'Марокко']]);
        $this->insert(['id' => 'MC', 'speak' => 'French'], ['en' => ['title' => 'Monaco'], 'mn' => ['title' => 'Монако']]);
        $this->insert(['id' => 'MD', 'speak' => '‎Moldovan'], ['mn' => ['title' => 'Молдав'], 'en' => ['title' => 'Moldova, Republic of']]);
        $this->insert(['id' => 'MG', 'speak' => 'Malagasy, French'], ['en' => ['title' => 'Madagascar'], 'mn' => ['title' => 'Мадагаскар']]);
        $this->insert(['id' => 'MH', 'speak' => 'English, Marshallese'], ['mn' => ['title' => 'Маршаллын арлууд'], 'en' => ['title' => 'Marshall Islands']]);
        $this->insert(['id' => 'MK', 'speak' => 'Macedonian'], ['mn' => ['title' => 'Македон'], 'en' => ['title' => 'Macedonia, The Former Yugoslav Republic of']]);
        $this->insert(['id' => 'ML', 'speak' => '‎Bambara'], ['mn' => ['title' => 'Мали'], 'en' => ['title' => 'Mali']]);
        $this->insert(['id' => 'MM', 'speak' => 'Burmese'], ['mn' => ['title' => 'Мьянмар'], 'en' => ['title' => 'Myanmar']]);
        $this->insert(['id' => 'MN', 'speak' => 'Монгол'], ['mn' => ['title' => 'Монгол'], 'en' => ['title' => 'Mongolia']]);
        $this->insert(['id' => 'MO', 'speak' => 'Chinese'], ['mn' => ['title' => 'Макоа'], 'en' => ['title' => 'Macau']]);
        $this->insert(['id' => 'MP', 'speak' => 'English, Chamorro'], ['mn' => ['title' => 'Өмнөд Маринагийн арлууд'], 'en' => ['title' => 'Northern Mariana Islands']]);
        $this->insert(['id' => 'MQ', 'speak' => 'French'], ['en' => ['title' => 'Martinique'], 'mn' => ['title' => 'Martinique']]);
        $this->insert(['id' => 'MR', 'speak' => 'Arabic'], ['mn' => ['title' => 'Мавритан'], 'en' => ['title' => 'Mauritania']]);
        $this->insert(['id' => 'MS', 'speak' => 'English'], ['en' => ['title' => 'Montserrat'], 'mn' => ['title' => 'Монтенегро']]);
        $this->insert(['id' => 'MT', 'speak' => 'English, Maltese'], ['en' => ['title' => 'Malta'], 'mn' => ['title' => 'Мальт']]);
        $this->insert(['id' => 'MU', 'speak' => ''], ['mn' => ['title' => 'Mauritius'], 'en' => ['title' => 'Mauritius']]);
        $this->insert(['id' => 'MV', 'speak' => ''], ['en' => ['title' => 'Maldives'], 'mn' => ['title' => 'Мальдив']]);
        $this->insert(['id' => 'MW', 'speak' => 'English'], ['en' => ['title' => 'Malawi'], 'mn' => ['title' => 'Малави']]);
        $this->insert(['id' => 'MX', 'speak' => 'Spanish'], ['mn' => ['title' => 'Мексик'], 'en' => ['title' => 'Mexico']]);
        $this->insert(['id' => 'MY', 'speak' => '‎Malaysian'], ['en' => ['title' => 'Malaysia'], 'mn' => ['title' => 'Малайз']]);
        $this->insert(['id' => 'MZ', 'speak' => 'Portuguese'], ['mn' => ['title' => 'Мозамбик'], 'en' => ['title' => 'Mozambique']]);
        $this->insert(['id' => 'NA', 'speak' => 'English, German'], ['mn' => ['title' => 'Намбиа'], 'en' => ['title' => 'Namibia']]);
        $this->insert(['id' => 'NC', 'speak' => 'French'], ['mn' => ['title' => 'Шинэ Кальдониа'], 'en' => ['title' => 'New Caledonia']]);
        $this->insert(['id' => 'NE', 'speak' => 'French'], ['mn' => ['title' => 'Нигер'], 'en' => ['title' => 'Niger']]);
        $this->insert(['id' => 'NF', 'speak' => 'English, Norfuk'], ['mn' => ['title' => 'Норфолк'], 'en' => ['title' => 'Norfolk Island']]);
        $this->insert(['id' => 'NG', 'speak' => 'English, Hausa, Yoruba, Igbo'], ['mn' => ['title' => 'Нигери'], 'en' => ['title' => 'Nigeria']]);
        $this->insert(['id' => 'NI', 'speak' => 'Spanish'], ['mn' => ['title' => 'Никарагуа'], 'en' => ['title' => 'Nicaragua']]);
        $this->insert(['id' => 'NL', 'speak' => 'Dutch, Frisian, Papiamento'], ['en' => ['title' => 'Netherlands'], 'mn' => ['title' => 'Нидерланд']]);
        $this->insert(['id' => 'NO', 'speak' => 'Norwegian, Bokmål, Nynorsk'], ['mn' => ['title' => 'Норвег'], 'en' => ['title' => 'Norway']]);
        $this->insert(['id' => 'NP', 'speak' => 'Nepali'], ['en' => ['title' => 'Nepal'], 'mn' => ['title' => 'Непал']]);
        $this->insert(['id' => 'NR', 'speak' => 'English, Nauruan'], ['en' => ['title' => 'Nauru'], 'mn' => ['title' => 'Науру']]);
        $this->insert(['id' => 'NU', 'speak' => 'Niuean English'], ['mn' => ['title' => 'Niue'], 'en' => ['title' => 'Niue']]);
        $this->insert(['id' => 'NZ', 'speak' => 'English'], ['en' => ['title' => 'New Zealand'], 'mn' => ['title' => 'Шинэ Зеланд']]);
        $this->insert(['id' => 'OM', 'speak' => 'Arabic'], ['mn' => ['title' => 'Оман'], 'en' => ['title' => 'Oman']]);
        $this->insert(['id' => 'PA', 'speak' => 'Spanish'], ['mn' => ['title' => 'Панама'], 'en' => ['title' => 'Panama']]);
        $this->insert(['id' => 'PE', 'speak' => 'Spanish, Aymara, Quechua'], ['en' => ['title' => 'Peru'], 'mn' => ['title' => 'Перу']]);
        $this->insert(['id' => 'PF', 'speak' => 'French'], ['mn' => ['title' => 'Францын Полинез'], 'en' => ['title' => 'French Polynesia']]);
        $this->insert(['id' => 'PG', 'speak' => 'English, Tok Pisin, Hiri Motu'], ['mn' => ['title' => 'Шинэ Гвиней'], 'en' => ['title' => 'Papua New Guinea']]);
        $this->insert(['id' => 'PH', 'speak' => 'English, Filipino'], ['mn' => ['title' => 'Филиппин'], 'en' => ['title' => 'Philippines']]);
        $this->insert(['id' => 'PK', 'speak' => 'Urdu, English'], ['mn' => ['title' => 'Пакистан'], 'en' => ['title' => 'Pakistan']]);
        $this->insert(['id' => 'PL', 'speak' => 'Polska'], ['mn' => ['title' => 'Польш'], 'en' => ['title' => 'Poland']]);
        $this->insert(['id' => 'PM', 'speak' => 'French'], ['mn' => ['title' => 'St. Pierre and Miquelon'], 'en' => ['title' => 'St. Pierre and Miquelon']]);
        $this->insert(['id' => 'PN', 'speak' => 'English'], ['mn' => ['title' => 'Pitcairn'], 'en' => ['title' => 'Pitcairn']]);
        $this->insert(['id' => 'PR', 'speak' => 'Spanish, English Destinations'], ['en' => ['title' => 'Puerto Rico'], 'mn' => ['title' => 'Пуарте Рика']]);
        $this->insert(['id' => 'PT', 'speak' => 'Portuguese'], ['mn' => ['title' => 'Португал'], 'en' => ['title' => 'Portugal']]);
        $this->insert(['id' => 'PW', 'speak' => 'Palauan English'], ['en' => ['title' => 'Palau'], 'mn' => ['title' => 'Палау']]);
        $this->insert(['id' => 'PY', 'speak' => 'Spanish, Paraguayan Guaraní'], ['en' => ['title' => 'Paraguay'], 'mn' => ['title' => 'Парагвай']]);
        $this->insert(['id' => 'QA', 'speak' => 'Arabic'], ['mn' => ['title' => 'Катар'], 'en' => ['title' => 'Qatar']]);
        $this->insert(['id' => 'RE', 'speak' => ''], ['en' => ['title' => 'Reunion'], 'mn' => ['title' => 'Reunion']]);
        $this->insert(['id' => 'RO', 'speak' => '‎Romanian '], ['mn' => ['title' => 'Румын'], 'en' => ['title' => 'Romania']]);
        $this->insert(['id' => 'RU', 'speak' => 'Русский'], ['mn' => ['title' => 'Орос'], 'en' => ['title' => 'Russian Federation']]);
        $this->insert(['id' => 'RW', 'speak' => 'Kinyarwanda, French'], ['en' => ['title' => 'Rwanda'], 'mn' => ['title' => 'Руанда']]);
        $this->insert(['id' => 'SA', 'speak' => 'Arabic'], ['mn' => ['title' => 'Саудын Араб'], 'en' => ['title' => 'Saudi Arabia']]);
        $this->insert(['id' => 'SB', 'speak' => 'English'], ['mn' => ['title' => 'Соломоны арлууд'], 'en' => ['title' => 'Solomon Islands']]);
        $this->insert(['id' => 'SC', 'speak' => 'French, English, Seselwa'], ['mn' => ['title' => 'Сэйшэль'], 'en' => ['title' => 'Seychelles']]);
        $this->insert(['id' => 'SD', 'speak' => 'Arabic, English'], ['mn' => ['title' => 'Судан'], 'en' => ['title' => 'Sudan']]);
        $this->insert(['id' => 'SE', 'speak' => '‎Swedish'], ['mn' => ['title' => 'Швед'], 'en' => ['title' => 'Sweden']]);
        $this->insert(['id' => 'SG', 'speak' => 'English, Tamil, Malay, Mandarin'], ['mn' => ['title' => 'Сингапур'], 'en' => ['title' => 'Singapore']]);
        $this->insert(['id' => 'SH', 'speak' => 'English'], ['mn' => ['title' => 'St. Helena'], 'en' => ['title' => 'St. Helena']]);
        $this->insert(['id' => 'SI', 'speak' => '‎Slovene'], ['en' => ['title' => 'Slovenia'], 'mn' => ['title' => 'Словен']]);
        $this->insert(['id' => 'SJ', 'speak' => 'Norwegian, Bokmål, Russian'], ['mn' => ['title' => 'Svalbard and Jan Mayen Islands'], 'en' => ['title' => 'Svalbard and Jan Mayen Islands']]);
        $this->insert(['id' => 'SK', 'speak' => '‎Slovak'], ['en' => ['title' => 'Slovakia (Slovak Republic)'], 'mn' => ['title' => 'Словек']]);
        $this->insert(['id' => 'SL', 'speak' => 'English'], ['en' => ['title' => 'Sierra Leone'], 'mn' => ['title' => 'Сьерра Леон']]);
        $this->insert(['id' => 'SM', 'speak' => 'Italian'], ['mn' => ['title' => 'Сан Марино'], 'en' => ['title' => 'San Marino']]);
        $this->insert(['id' => 'SN', 'speak' => 'French'], ['en' => ['title' => 'Senegal'], 'mn' => ['title' => 'Сенегал']]);
        $this->insert(['id' => 'SO', 'speak' => 'Somali, Arabic'], ['mn' => ['title' => 'Сомали'], 'en' => ['title' => 'Somalia']]);
        $this->insert(['id' => 'SR', 'speak' => 'Dutch'], ['mn' => ['title' => 'Суринам'], 'en' => ['title' => 'Suriname']]);
        $this->insert(['id' => 'ST', 'speak' => 'Portuguese'], ['en' => ['title' => 'Sao Tome and Principe'], 'mn' => ['title' => 'Сан Томе Принсип']]);
        $this->insert(['id' => 'SV', 'speak' => 'Spanish'], ['mn' => ['title' => 'Эль Сальвадор'], 'en' => ['title' => 'El Salvador']]);
        $this->insert(['id' => 'SY', 'speak' => 'Arabic'], ['mn' => ['title' => 'Сири'], 'en' => ['title' => 'Syrian Arab Republic']]);
        $this->insert(['id' => 'SZ', 'speak' => 'English, Swati'], ['mn' => ['title' => 'Swaziland'], 'en' => ['title' => 'Swaziland']]);
        $this->insert(['id' => 'TC', 'speak' => 'English'], ['mn' => ['title' => 'Turks and Caicos Islands'], 'en' => ['title' => 'Turks and Caicos Islands']]);
        $this->insert(['id' => 'TD', 'speak' => 'French, Arabic'], ['mn' => ['title' => 'Чад'], 'en' => ['title' => 'Chad']]);
        $this->insert(['id' => 'TF', 'speak' => 'French'], ['mn' => ['title' => 'French Southern Territories'], 'en' => ['title' => 'French Southern Territories']]);
        $this->insert(['id' => 'TG', 'speak' => 'French'], ['mn' => ['title' => 'Того'], 'en' => ['title' => 'Togo']]);
        $this->insert(['id' => 'TH', 'speak' => 'Thai'], ['en' => ['title' => 'Thailand'], 'mn' => ['title' => 'Тайланд']]);
        $this->insert(['id' => 'TJ', 'speak' => '‎Tajiks'], ['mn' => ['title' => 'Тажикстан'], 'en' => ['title' => 'Tajikistan']]);
        $this->insert(['id' => 'TK', 'speak' => 'Tokelauan'], ['en' => ['title' => 'Tokelau'], 'mn' => ['title' => 'Tokelau']]);
        $this->insert(['id' => 'TM', 'speak' => 'Turkmen'], ['en' => ['title' => 'Turkmenistan'], 'mn' => ['title' => 'Туркменстан']]);
        $this->insert(['id' => 'TN', 'speak' => 'Arabic'], ['mn' => ['title' => 'Тунис'], 'en' => ['title' => 'Tunisia']]);
        $this->insert(['id' => 'TO', 'speak' => 'English, Tongan'], ['en' => ['title' => 'Tonga'], 'mn' => ['title' => 'Тонга']]);
        $this->insert(['id' => 'TR', 'speak' => 'Turkish'], ['mn' => ['title' => 'Турк'], 'en' => ['title' => 'Turkey']]);
        $this->insert(['id' => 'TT', 'speak' => 'English'], ['mn' => ['title' => 'Бүгд Найрамдах Тринидад Тобаго'], 'en' => ['title' => 'Trinidad and Tobago']]);
        $this->insert(['id' => 'TV', 'speak' => 'Tuvaluan English'], ['en' => ['title' => 'Tuvalu'], 'mn' => ['title' => 'Тувалу']]);
        $this->insert(['id' => 'TW', 'speak' => 'Chinese: 臺灣省 or 台灣省'], ['mn' => ['title' => 'Тайван'], 'en' => ['title' => 'Taiwan, Province of China']]);
        $this->insert(['id' => 'TZ', 'speak' => 'Swahili'], ['mn' => ['title' => 'Бүгд Найрамдах Танзани'], 'en' => ['title' => 'Tanzania, United Republic of']]);
        $this->insert(['id' => 'UA', 'speak' => ''], ['mn' => ['title' => 'Украйн'], 'en' => ['title' => 'Ukraine']]);
        $this->insert(['id' => 'UG', 'speak' => ''], ['mn' => ['title' => 'Уганда'], 'en' => ['title' => 'Uganda']]);
        $this->insert(['id' => 'UM', 'speak' => ''], ['en' => ['title' => 'United States Minor Outlying Islands'], 'mn' => ['title' => 'United States Minor Outlying Islands']]);
        $this->insert(['id' => 'US', 'speak' => 'English'], ['mn' => ['title' => 'Америкийн Нэгдсэн Улс'], 'en' => ['title' => 'United States']]);
        $this->insert(['id' => 'UY', 'speak' => ''], ['mn' => ['title' => 'Урагвай'], 'en' => ['title' => 'Uruguay']]);
        $this->insert(['id' => 'UZ', 'speak' => ''], ['en' => ['title' => 'Uzbekistan'], 'mn' => ['title' => 'Узбекстан']]);
        $this->insert(['id' => 'VA', 'speak' => ''], ['mn' => ['title' => 'Ватикан'], 'en' => ['title' => 'Holy See (Vatican City State)']]);
        $this->insert(['id' => 'VC', 'speak' => ''], ['en' => ['title' => 'Saint Vincent and the Grenadines'], 'mn' => ['title' => 'Сент Винсент Гренадин']]);
        $this->insert(['id' => 'VE', 'speak' => ''], ['en' => ['title' => 'Venezuela'], 'mn' => ['title' => 'Венесуэл']]);
        $this->insert(['id' => 'VG', 'speak' => ''], ['mn' => ['title' => 'Виржины арлууд (Британы)'], 'en' => ['title' => 'Virgin Islands (British)']]);
        $this->insert(['id' => 'VI', 'speak' => ''], ['en' => ['title' => 'Virgin Islands (U.S.)'], 'mn' => ['title' => 'Виржины арлууд (АНУ)']]);
        $this->insert(['id' => 'VN', 'speak' => ''], ['mn' => ['title' => 'Вьетнам'], 'en' => ['title' => 'Viet Nam']]);
        $this->insert(['id' => 'VU', 'speak' => ''], ['mn' => ['title' => 'Вануату'], 'en' => ['title' => 'Vanuatu']]);
        $this->insert(['id' => 'WF', 'speak' => ''], ['en' => ['title' => 'Wallis and Futuna Islands'], 'mn' => ['title' => 'Wallis and Futuna Islands']]);
        $this->insert(['id' => 'WS', 'speak' => ''], ['mn' => ['title' => 'Самоа'], 'en' => ['title' => 'Samoa']]);
        $this->insert(['id' => 'YE', 'speak' => ''], ['mn' => ['title' => 'Йемен'], 'en' => ['title' => 'Yemen']]);
        $this->insert(['id' => 'YT', 'speak' => ''], ['mn' => ['title' => 'Mayotte'], 'en' => ['title' => 'Mayotte']]);
        $this->insert(['id' => 'ZA', 'speak' => ''], ['mn' => ['title' => 'Өмнөд Африк'], 'en' => ['title' => 'South Africa']]);
        $this->insert(['id' => 'ZM', 'speak' => ''], ['en' => ['title' => 'Zambia'], 'mn' => ['title' => 'Замби']]);
        $this->insert(['id' => 'ZW', 'speak' => ''], ['mn' => ['title' => 'Зинбабве'], 'en' => ['title' => 'Zimbabwe']]);
        
        $this->setForeignKeyChecks(true);
    }
}
