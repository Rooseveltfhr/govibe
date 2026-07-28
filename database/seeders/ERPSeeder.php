<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ERPSeeder extends Seeder
{
    public function run(): void
    {
        // Business Units
        $units = [
            ['name'=>'Coworking',          'icon'=>'bi-building',        'color'=>'#1e3a5f', 'description'=>'Espaces de travail partagés'],
            ['name'=>'Academy',            'icon'=>'bi-mortarboard-fill','color'=>'#d4a017', 'description'=>'Formation et développement professionnel'],
            ['name'=>'AI Lab',             'icon'=>'bi-cpu-fill',        'color'=>'#7c3aed', 'description'=>'Laboratoire Intelligence Artificielle'],
            ['name'=>'Media',              'icon'=>'bi-camera-video-fill','color'=>'#dc2626','description'=>'Production audiovisuelle et contenu'],
            ['name'=>'Digital Services',   'icon'=>'bi-globe2',          'color'=>'#0891b2', 'description'=>'Services numériques et marketing'],
            ['name'=>'Software Dev',       'icon'=>'bi-code-slash',      'color'=>'#059669', 'description'=>'Développement de logiciels sur mesure'],
            ['name'=>'Mobile Dev',         'icon'=>'bi-phone-fill',      'color'=>'#d97706', 'description'=>'Applications mobiles iOS et Android'],
            ['name'=>'Website Dev',        'icon'=>'bi-window-stack',    'color'=>'#4f46e5', 'description'=>'Création et refonte de sites web'],
            ['name'=>'Cybersecurity',      'icon'=>'bi-shield-fill-check','color'=>'#be123c','description'=>'Sécurité informatique et audit'],
            ['name'=>'AI Agents',          'icon'=>'bi-robot',           'color'=>'#6d28d9', 'description'=>'Agents IA et automatisation intelligente'],
            ['name'=>'Automation',         'icon'=>'bi-gear-wide-connected','color'=>'#0284c7','description'=>'Automatisation de processus métier'],
            ['name'=>'Startup Incubator',  'icon'=>'bi-rocket-takeoff-fill','color'=>'#ea580c','description'=>'Incubation et accélération de startups'],
            ['name'=>'Events',             'icon'=>'bi-calendar-event-fill','color'=>'#db2777','description'=>'Événements et conférences'],
            ['name'=>'Consulting',         'icon'=>'bi-briefcase-fill',  'color'=>'#64748b', 'description'=>'Conseil stratégique et transformation digitale'],
        ];

        foreach ($units as $unit) {
            $slug = \Illuminate\Support\Str::slug($unit['name']);
            BusinessUnit::firstOrCreate(
                ['name' => $unit['name']],
                $unit + ['active' => true, 'slug' => $slug]
            );
        }

        // Service categories
        $categories = [
            ['name'=>'Formation',        'icon'=>'bi-mortarboard', 'color'=>'#d4a017'],
            ['name'=>'Développement',    'icon'=>'bi-code-slash',  'color'=>'#059669'],
            ['name'=>'Design',           'icon'=>'bi-palette-fill','color'=>'#7c3aed'],
            ['name'=>'Consulting',       'icon'=>'bi-briefcase',   'color'=>'#0891b2'],
            ['name'=>'Coworking',        'icon'=>'bi-building',    'color'=>'#1e3a5f'],
            ['name'=>'Marketing Digital','icon'=>'bi-graph-up',    'color'=>'#dc2626'],
            ['name'=>'Cybersécurité',    'icon'=>'bi-shield-check','color'=>'#be123c'],
            ['name'=>'IA & Automation',  'icon'=>'bi-robot',       'color'=>'#6d28d9'],
        ];

        $catMap = [];
        foreach ($categories as $cat) {
            $c = ServiceCategory::firstOrCreate(
                ['name' => $cat['name']],
                $cat + ['slug' => \Illuminate\Support\Str::slug($cat['name'])]
            );
            $catMap[$cat['name']] = $c->id;
        }

        // Services
        $services = [
            ['name'=>'Formation Laravel Avancé',       'price'=>25000, 'unit'=>'session', 'category'=>'Formation',   'description'=>'Formation intensive Laravel 12 pour développeurs'],
            ['name'=>'Formation React Native',         'price'=>20000, 'unit'=>'session', 'category'=>'Formation',   'description'=>'Développement mobile cross-platform'],
            ['name'=>'Formation IA & Machine Learning','price'=>35000, 'unit'=>'session', 'category'=>'Formation',   'description'=>'Introduction pratique à l\'IA et ML'],
            ['name'=>'Formation Cybersécurité',        'price'=>30000, 'unit'=>'session', 'category'=>'Formation',   'description'=>'Sécurité informatique fondamentale'],
            ['name'=>'Développement Site Web',         'price'=>50000, 'unit'=>'project', 'category'=>'Développement','description'=>'Site vitrine ou e-commerce professionnel'],
            ['name'=>'Application Mobile',             'price'=>150000,'unit'=>'project', 'category'=>'Développement','description'=>'App iOS/Android sur mesure'],
            ['name'=>'Logiciel ERP/CRM',               'price'=>200000,'unit'=>'project', 'category'=>'Développement','description'=>'Système de gestion entreprise'],
            ['name'=>'API RESTful',                    'price'=>30000, 'unit'=>'project', 'category'=>'Développement','description'=>'Développement API backend'],
            ['name'=>'Design UI/UX',                   'price'=>15000, 'unit'=>'project', 'category'=>'Design',       'description'=>'Design interface utilisateur'],
            ['name'=>'Identité Visuelle',              'price'=>20000, 'unit'=>'project', 'category'=>'Design',       'description'=>'Logo, charte graphique, branding'],
            ['name'=>'Consulting Stratégie Digitale',  'price'=>5000,  'unit'=>'hour',    'category'=>'Consulting',   'description'=>'Conseil en transformation numérique'],
            ['name'=>'Audit Informatique',             'price'=>25000, 'unit'=>'project', 'category'=>'Cybersécurité','description'=>'Audit sécurité et vulnérabilités'],
            ['name'=>'Coworking Jour',                 'price'=>1500,  'unit'=>'day',     'category'=>'Coworking',    'description'=>'Accès espace coworking journée'],
            ['name'=>'Coworking Mois',                 'price'=>15000, 'unit'=>'month',   'category'=>'Coworking',    'description'=>'Abonnement coworking mensuel'],
            ['name'=>'Salle de Réunion',               'price'=>2000,  'unit'=>'hour',    'category'=>'Coworking',    'description'=>'Location salle de réunion équipée'],
            ['name'=>'Agent IA Custom',                'price'=>40000, 'unit'=>'project', 'category'=>'IA & Automation','description'=>'Développement agent IA sur mesure'],
            ['name'=>'Automation Workflow',            'price'=>20000, 'unit'=>'project', 'category'=>'IA & Automation','description'=>'Automatisation de processus métier'],
            ['name'=>'Gestion Réseaux Sociaux',        'price'=>8000,  'unit'=>'month',   'category'=>'Marketing Digital','description'=>'Community management et contenu'],
            ['name'=>'Campagne Publicitaire',          'price'=>15000, 'unit'=>'month',   'category'=>'Marketing Digital','description'=>'Publicité Facebook, Instagram, Google'],
        ];

        foreach ($services as $s) {
            $cat = $catMap[$s['category']] ?? null;
            Service::firstOrCreate(
                ['name' => $s['name']],
                [
                    'price'       => $s['price'],
                    'unit'        => $s['unit'],
                    'category_id' => $cat,
                    'description' => $s['description'],
                    'is_active'   => true,
                    'slug'        => \Illuminate\Support\Str::slug($s['name']),
                ]
            );
        }

        // Sample clients
        $clients = [
            ['name'=>'Entreprise Alpha SA',    'type'=>'company',     'status'=>'active',  'email'=>'contact@alpha.ht',    'phone'=>'509-3700-0001','city'=>'Port-au-Prince'],
            ['name'=>'Marie Pierre',           'type'=>'individual',  'status'=>'active',  'email'=>'marie@gmail.com',      'phone'=>'509-3700-0002','city'=>'Pétion-Ville'],
            ['name'=>'ONG Développement Haiti','type'=>'ngo',         'status'=>'active',  'email'=>'info@ongdev.ht',       'phone'=>'509-3700-0003','city'=>'Cap-Haïtien'],
            ['name'=>'Université Caraïbe',     'type'=>'university',  'status'=>'prospect','email'=>'direction@unicaraibe.edu','phone'=>'509-3700-0004','city'=>'Port-au-Prince'],
            ['name'=>'Ministère Education',    'type'=>'government',  'status'=>'active',  'email'=>'info@menfp.gouv.ht',   'phone'=>'509-3700-0005','city'=>'Port-au-Prince'],
        ];

        foreach ($clients as $i => $c) {
            Client::firstOrCreate(
                ['email' => $c['email']],
                $c + ['reference_number' => 'CLI-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT)]
            );
        }
    }
}
