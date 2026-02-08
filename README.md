Web prodavnica sa receptima

Aplikacija za jednostavnu veb prodavnicu sa receptima i sastojcima. Korisnici mogu da pretražuju recepte i sastojke, dobiju predloge recepata na osnovu unetih sastojaka, kao i da iz izabranog recepta automatski formiraju korpu. Administratori upravljaju celokupnim sadržajem i porudžbinama.

⸻

Bezbednost i kontrola pristupa

Aplikacija koristi Laravel Sanctum za autentikaciju i izdavanje Bearer tokena, pa su sve operacije koje menjaju podatke dostupne isključivo
prijavljenim korisnicima. Uloge su podeljene na korisnike i administratore, a dozvole su definisane na nivou kontrolera i pojedinačnih akcija.

Korisnici mogu slobodno da istražuju javne sadržaje — liste i detalje recepata i sastojaka — uz podršku filtera i paginacije. Kada je reč o kupovini, korisnici mogu da formiraju korpu i kreiraju porudžbine, kao i da vide isključivo svoje porudžbine i njihove detalje. 

Administratori imaju proširene privilegije. Pored pregleda javnih sadržaja, administrator upravlja celokupnim katalogom: kreira, ažurira i briše recepte i sastojke. U domenu porudžbina, administrator ima uvid u sve porudžbine i može da menja njihov status, što omogućava operativnu obradu narudžbina. Kreiranje same porudžbine ostavljeno je krajnjim korisnicima (kupcima), čime se razdvajaju uloge kupovine i administracije. Po potrebi, dostupne su i administratorske rute za upravljanje porudžbinama.

Javne rute obuhvataju isključivo čitanje: pregled lista i pojedinačnih recepata/sastojaka. Sve ostalo — rad sa korpom, kreiranje porudžbina, uređivanje recepta/sastojka i administrativne operacije — zahteva validan Sanctum token.

⸻

Instalacija i pokretanje

Preduslovi
	•	PHP 8.2+
	•	Composer 
	•	MySQL 
	•	Node.js 18+ 
	•	Docker Desktop 

Koraci

# 1) Kloniranje
git clone <repo-url>
cd <repo-folder>

# 2) PHP zavisnosti
composer install

# 3) Kreiranje .env fajla i aplikacionog ključa
cp .env.example .env
php artisan key:generate

# 4) Podesite .env (DB_* promenljive)

# 5) Pokretanje docker-compose.yml file-a
docker compose up -d --build

# 6) Migracije i seederi 
sledeće komande je potrebno izvršiti u backend kontejneru:
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
docker compose exec backend php artisan storage:link

# Frontend aplikacija je dostupna na http://127.0.0.1:5173
# Backend aplikacija je dostupna na http://127.0.0.1:8000
⸻

Swagger (OpenAPI) dokumentacija

Projekt koristi paket L5-Swagger.

Generisanje i pregled:

1 - composer require "darkaonline/l5-swagger"
2 - php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"

# Generišite OpenAPI JSON/YAML
php artisan l5-swagger:generate

Zatim otvorite /api/documentation u browser-u (npr. http://127.0.0.1:8000/api/documentation).

U Swagger UI možete izvršavati pozive direktno iz browser-a.

⸻
