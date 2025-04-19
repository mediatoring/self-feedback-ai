# AI Feedback & Reference Plugin

WordPress plugin pro vytváření reflexí a referencí s využitím umělé inteligence (GPT-4.1).

## Popis

Plugin umožňuje uživatelům vytvářet a spravovat osobní reflexe ze školení a generovat reference s pomocí umělé inteligence. Je určen pro účastníky školení, kteří chtějí systematicky zaznamenávat své poznatky a získávat AI zpětnou vazbu.

## Hlavní funkce

### 1. Zápisník reflexí
- Vytváření nových zápisků s následujícími sekcemi:
  - Co nového jste se naučili?
  - Jak byste to vysvětlili kolegovi?
  - Kde to můžete využít zítra?
- Automatická AI zpětná vazba ke každému zápisku
- Historie zápisků s přehledným zobrazením
- Možnost zaslání zápisků na e-mail

### 2. Generování referencí
- Dva režimy vytváření referencí:
  - Manuální psaní
  - Generování pomocí AI na základě odpovědí na klíčové otázky
- Možnost přidání osobních údajů a fotografie
- Přehledné zobrazení vytvořených referencí

### 3. Administrace
- Nastavení API klíče pro OpenAI
- Správa reflexí a referencí
- Export dat
- Analytika

## Instalace

1. Stáhněte plugin
2. Nahrajte složku pluginu do adresáře `/wp-content/plugins/`
3. Aktivujte plugin v administraci WordPress
4. Zadejte API klíč OpenAI v nastavení pluginu

## Použití

### Zápisník reflexí
1. Vložte shortcode `[self_feedback_form]` na požadovanou stránku
2. Zadejte svůj e-mail pro začátek nového zápisníku
3. Vytvářejte nové zápisky pomocí formuláře
4. Prohlížejte si historii zápisků s AI zpětnou vazbou
5. Po dokončení můžete zápisky zaslat na e-mail

### Reference
1. V sekci "Reference" vyberte režim vytváření (manuální/AI)
2. Vyplňte požadované údaje
3. Přidejte osobní údaje a volitelně fotku
4. Vytvořte referenci

## Struktura pluginu

- `self-feedback-ai.php` - Hlavní soubor pluginu
- `includes/` - Složka s třídami pro jednotlivé komponenty:
  - `class-ai-feedback-analytics-core.php` - Základní funkce
  - `class-ai-feedback-analytics-data.php` - Správa dat
  - `class-ai-feedback-analytics-admin.php` - Administrační rozhraní
  - `class-ai-feedback-analytics-export.php` - Export dat
  - `class-ai-feedback-analytics-api.php` - API integrace
- `assets/` - Složka s CSS a JavaScript soubory

## Požadavky

- WordPress 5.0 nebo novější
- PHP 7.4 nebo novější
- API klíč OpenAI pro přístup k GPT-4.1

## Bezpečnost

- Plugin používá standardní WordPress bezpečnostní mechanismy
- API klíč je uložen v databázi WordPress
- Všechny uživatelské vstupy jsou validovány a escapovány

## Podpora

Pro podporu nebo nahlášení problémů kontaktujte autora pluginu.

## Licence

Plugin je distribuován pod licencí GPL v2 nebo novější. 