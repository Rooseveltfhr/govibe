<?php

namespace Modules\Agents\Support;

use Modules\Agents\DTO\AgentDefinition;
use Modules\Agents\Templates\AgentTemplateRegistry;

/**
 * Ajan sipò LOUVIA a — konesans platfòm nan, pa konesans yon machann.
 *
 * Li bati sou menm modèl « chatbot » nou vann lan: zewo zouti ekri, kidonk
 * li pa ka pwomèt ni fè anyen. Sa li konnen, li konnen l isit la — epi sa
 * li pa konnen, li voye l sou WhatsApp. Nou pa vle yon sipò ki envante yon
 * pri ni yon dat.
 */
final class PlatformSupportAgent
{
    public const WHATSAPP = '+509 3398 8754';

    public static function definition(?AgentTemplateRegistry $templates = null): AgentDefinition
    {
        $templates ??= new AgentTemplateRegistry;

        return $templates->make(
            sector: 'support',
            key: 'louvia-support',
            name: 'Sipò LOUVIA',
            knowledge: self::knowledge(),
            channels: ['web'],
            languages: ['ht', 'fr', 'en', 'es'],
            handoffTo: self::WHATSAPP,
        );
    }

    /** @return array<string, string> */
    public static function knowledge(): array
    {
        return [
            'produit' => 'LOUVIA se yon platfòm GOVIBE ki bay biznis ayisyen ajan IA: '
                .'chatbot ki reponn kesyon, epi ajan ki pran kòmand ak randevou.',
            'modeles' => 'Modèl ki disponib: Restoran, Klinik/Sante, Lekòl (ajan ki aji), '
                .'Sipò kliyan, Asistan sit entènèt, Akèy WhatsApp (chatbot ki reponn).',
            'langues' => 'Ajan yo pale kreyòl, franse, anglè ak panyòl.',
            'canaux' => 'Entegrasyon: WhatsApp, bwat chat sou sit entènèt la, epi vwa. '
                .'Chak entegrasyon mande yon konfigirasyon ak biznis lan.',
            'creation' => 'De chemen: biznis lan kreye ajan an li menm sou paj « Kreye », '
                .'oswa li kòmande epi yon ekspè GOVIBE monte l pou li.',
            'securite' => 'Yon ajan pa janm envante yon pri, yon orè oswa yon pwodwi: '
                .'li sèvi sèlman ak enfòmasyon biznis lan ba li. Aksyon ki angaje '
                .'(kòmand, randevou) mande yon konfimasyon.',
            'contact' => 'WhatsApp GOVIBE: '.self::WHATSAPP,
            'prix' => 'Pri yo depann de modèl la ak entegrasyon yo. Pa bay yon chif: '
                .'mande moun nan ekri sou WhatsApp pou yon devi.',
        ];
    }
}
