<?php

declare(strict_types=1);

namespace App\Repositories;

final class ModeratorRepository
{
    private array $moderators = [
        'carlos' => [
            'name' => 'Carlos',
            'role' => 'Fundador / Gestão de Tecnologia e Plataforma',
            'tone' => 'founder',
            'avatar' => '/assets/img/moderadores/carlos.webp',
            'summary' => 'Responsável pela gestão geral da tecnologia e da plataforma, liderando equipes, definindo estratégias e garantindo a qualidade e evolução do produto.',
            'headline' => 'Responsável pela gestão geral da tecnologia e pela evolução da plataforma Bolfer.',
            'bio' => 'Atua como ponte entre desenvolvimento, organização e visão de crescimento da Bolfer, alinhando equipes, direcionando prioridades e garantindo que a plataforma evolua com qualidade e consistência.',
            'impact' => 'Conecta tecnologia, organização e estratégia de crescimento, fortalecendo a base da plataforma e guiando a evolução do produto.',
            'skills' => [
                'Gestão de tecnologia',
                'Liderança de equipes',
                'Estratégia de produto',
                'Organização da plataforma',
                'Evolução do produto',
            ],
            'focus' => [
                'Gestão geral da plataforma',
                'Estratégia e crescimento',
                'Qualidade e evolução do produto',
            ],
        ],
        'bruno' => [
            'name' => 'Bruno',
            'role' => 'Moderador / Analista de Suporte e Operações',
            'tone' => 'dev',
            'avatar' => '/assets/img/moderadores/bruno.webp',
            'summary' => 'Atua no suporte técnico e operacional da plataforma, auxiliando na manutenção dos sistemas e no atendimento da comunidade.',
            'headline' => 'Responsável por acompanhar tickets, resolver demandas do dia a dia e garantir a estabilidade dos serviços.',
            'bio' => 'Atua no suporte técnico e operacional da plataforma, auxiliando na manutenção dos sistemas e no atendimento da comunidade. É responsável por acompanhar tickets, resolver demandas do dia a dia e garantir que os serviços funcionem de forma estável.',
            'impact' => 'Fortalece a operação diária da Bolfer, mantém o suporte organizado e ajuda a comunidade com respostas rápidas e serviços estáveis.',
            'skills' => [
                'Suporte técnico',
                'Suporte operacional',
                'Manutenção de sistemas',
                'Atendimento da comunidade',
                'Acompanhamento de tickets',
            ],
            'focus' => [
                'Resolução de demandas do dia a dia',
                'Acompanhamento de tickets',
                'Estabilidade dos serviços',
            ],
        ],
        'joao' => [
            'name' => 'João',
            'role' => 'Moderador / Desenvolvedor Back-end',
            'tone' => 'tech',
            'avatar' => '/assets/img/moderadores/joao.webp',
            'summary' => 'Atua no desenvolvimento da base técnica da plataforma, com foco em back-end, criando soluções, organizando a lógica do sistema e garantindo o funcionamento correto das funcionalidades.',
            'headline' => 'Responsável por estruturar a lógica do sistema e fortalecer a base técnica da plataforma Bolfer.',
            'bio' => 'Atua no desenvolvimento da base técnica da plataforma, com foco em back-end, criando soluções, organizando a lógica do sistema e garantindo o funcionamento correto das funcionalidades. Também contribui com ajustes no front-end quando necessário.',
            'impact' => 'Fortalece a estrutura técnica da Bolfer, mantém a lógica do sistema organizada e ajuda a garantir entregas mais estáveis e consistentes.',
            'skills' => [
                'Desenvolvimento back-end',
                'Lógica de sistema',
                'Estruturação de funcionalidades',
                'Soluções técnicas',
                'Ajustes de front-end',
                'Base técnica da plataforma',
            ],
            'focus' => [
                'Desenvolvimento da base técnica',
                'Organização da lógica do sistema',
                'Funcionamento correto das funcionalidades',
            ],
        ],
        'marcus' => [
            'name' => 'Marcus',
            'role' => 'Moderador / Analista de Operações e Marketing',
            'tone' => 'ops',
            'avatar' => '/assets/img/moderadores/marcus.webp',
            'summary' => 'Responsável pelas operações internas da plataforma, atuando no controle de estoque, fluxo de caixa e apoio estratégico em ações de marketing.',
            'headline' => 'Contribui para a organização dos processos e para o funcionamento eficiente da comunidade.',
            'bio' => 'Responsável pelas operações internas da plataforma, atuando no controle de estoque, fluxo de caixa e apoio estratégico em ações de marketing. Contribui para a organização dos processos e para o funcionamento eficiente da comunidade.',
            'impact' => 'Ajuda a manter a operação interna organizada, fortalece a eficiência dos processos e apoia decisões importantes de marketing e rotina.',
            'skills' => [
                'Controle de estoque',
                'Fluxo de caixa',
                'Operações internas',
                'Organização de processos',
                'Apoio estratégico em marketing',
            ],
            'focus' => [
                'Controle operacional',
                'Fluxo de caixa',
                'Ações de marketing',
            ],
        ],
        'wolf' => [
            'name' => 'Wolf',
            'role' => 'Moderador / Analista de Marketing e Crescimento',
            'tone' => 'growth',
            'avatar' => '/assets/img/moderadores/wolf.webp',
            'summary' => 'Responsável pelas estratégias de marketing e crescimento da comunidade, atuando na divulgação dos projetos, fortalecimento da marca e atração de novos membros.',
            'headline' => 'Atua no crescimento da comunidade com foco em marca, divulgação e expansão.',
            'bio' => 'Responsável pelas estratégias de marketing e crescimento da comunidade, atuando na divulgação dos projetos, fortalecimento da marca e atração de novos membros.',
            'impact' => 'Amplia o alcance da Bolfer, fortalece a presença da marca e ajuda a comunidade a crescer com mais consistência.',
            'skills' => [
                'Marketing digital',
                'Estratégias de crescimento',
                'Fortalecimento de marca',
                'Divulgação de projetos',
                'Atração de novos membros',
            ],
            'focus' => [
                'Crescimento da comunidade',
                'Divulgação dos projetos',
                'Fortalecimento da marca',
            ],
        ],
    ];

    public function all(): array
    {
        return $this->moderators;
    }

    public function find(string $slug): ?array
    {
        $key = strtolower(trim($slug));

        return $this->moderators[$key] ?? null;
    }
}
