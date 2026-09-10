# CrismaQuest — Gameplay canônico 2026–2027

Estado: **fechado para implementação e produção**  
Temporada: **10/09/2026 a 09/02/2027**  
Fuso: **America/Fortaleza**  
Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza

## Princípios pastorais

- XP mede participação e constância, nunca fé, santidade ou valor pessoal.
- Ausência não retira XP nem destrói recompensas já obtidas.
- Não existe ranking individual público.
- Conteúdo catequético essencial não é bloqueado por XP.
- Reflexões pessoais podem permanecer privadas.
- O app não é espaço para confissão nem para coleta de intimidade espiritual.
- Lúmens são moeda puramente interna e não compram graça, oração, sacramentos ou objetos religiosos reais.
- Toda mecânica social deve favorecer comunhão, não pressão ou competição.

## Loop central

Missão → XP/Lúmens → Chama → avanço na Jornada → baús/cartas → coleção/cosméticos → comunidade → próxima missão.

Todo botão visível precisa executar uma ação real e persistida no servidor.

## Jornada

22 etapas, seis capítulos:

1. O Chamado — etapas 1–4
2. Quem é Jesus? — etapas 5–8
3. A Igreja — etapas 9–11
4. Os Sacramentos — etapas 12–15
5. Vida em Cristo — etapas 16–19
6. Oração e Missão — etapas 20–22

Uma etapa é concluída quando todas as missões-base ativas daquela etapa foram concluídas. Missões antigas continuam disponíveis até 09/02/2027 para permitir recuperação sem fabricar Chama retroativa.

## Conteúdo

- 44 missões-base: duas por etapa
- 6 Grandes Missões de capítulo
- 6 missões especiais
- 60 Centelhas diárias aprovadas
- 56 missões autorais principais no total

Tipos: Palavra Viva, Entenda a Fé, Desafio da Fé, Evangelho em Ação, Igreja por Dentro, Testemunhas, Grande Missão e Missão Especial.

### Recompensas-base

| Tipo | XP | Lúmens |
|---|---:|---:|
| Centelha | 5 | 1 |
| Palavra Viva | 10 | 3 |
| Quiz | 10 | 2 |
| Acerto de quiz | +5 | 0 |
| Reflexão | 15 | 4 |
| Evangelho em Ação | 15 | 5 |
| Igreja por Dentro | 10 | 3 |
| Testemunhas | 10–15 | 3–5 |
| Grande Missão | 30 | 15 |
| Presença | 50 | 8 |
| Missão especial | 10–40 | 2–12 |

Somente as três primeiras Centelhas de cada semana concedem XP/Lúmens. As demais mantêm a Chama.

## Níveis

| Nível | Nome | XP mínimo |
|---:|---|---:|
| 1 | Peregrino | 0 |
| 2 | Caminhante | 120 |
| 3 | Discípulo | 300 |
| 4 | Servidor | 520 |
| 5 | Mensageiro | 780 |
| 6 | Missionário | 1.080 |
| 7 | Testemunha | 1.420 |
| 8 | Enviado | 1.800 |

Não existe nível espiritual acima de Enviado. XP posterior rende apenas marcos/cosméticos.

## Chama

Qualquer missão válida ou Centelha do dia qualifica atividade no fuso de Fortaleza. Operações são idempotentes.

Marcos:

- 3 dias: +3 Lúmens
- 7 dias: Escudo da Chama
- 14 dias: Baú Brasa
- 30 dias: Chama Dourada
- 60 dias: carta em edição iluminada
- 90 dias: moldura especial

Escudo cobre automaticamente um único dia perdido entre atividades. Perder a sequência nunca apaga recorde, XP, cartas ou conquistas.

Recesso de 13/12/2026 a 22/01/2027: Chama congelada automaticamente.

## Vela de Intercessão

- gratuita
- no máximo uma enviada por pessoa por semana
- destinatário pode armazenar no máximo duas
- validade de sete dias
- cobre um dia perdido elegível
- não concede recompensa ao remetente
- não pode ser enviada para si mesmo

A mensagem pastoral é apoio e oração, não comercialização da oração.

## Rosário da Jornada

- custo: 90 Lúmens
- recupera um único dia perdido nas últimas 48h
- exige que já existisse uma sequência anterior
- cooldown: 30 dias
- não concede XP
- não acumula
- item simbólico do jogo

## Baús de XP

200 Semente, 400 Caminho, 600 Comunhão, 800 Testemunhas, 1.000 Serviço, 1.200 Palavra, 1.400 Esperança, 1.600 Missão, 1.800 Envio.

Abertura é idempotente. Baús podem conceder Lúmens, cartas, Escudo e cosméticos. Não há dinheiro real.

## Cartas e padroeiros

Carlo Acutis e Santa Joana d'Arc são padroeiros da turma.

- Carlo: carta garantida na missão correspondente do capítulo 2.
- Joana: carta garantida na Grande Missão comunitária do capítulo 3.
- cartas dos padroeiros não podem ser presenteadas
- edição visual não representa grau de santidade

Edições: Normal, Iluminada e, posteriormente, Comemorativa.

## Trocas e presentes

Presentear carta:
- somente duplicata
- nunca a última cópia
- máximo uma carta por semana
- sem XP/Lúmens para quem envia
- padroeiros bloqueados

Troca:
- 1×1
- as duas cartas precisam ser duplicadas
- três propostas por semana
- validade de 72 horas
- aceite do destinatário
- transação atômica

## Loja de Lúmens

Presentes sociais: Selo Paz e Bem, Estrela da Jornada, Rosa de Teresinha, Vela da Amizade, Pomba da Paz, Marca-página da Palavra, Flores de Maria, Pão Partilhado e Luz do Caminho.

Cosméticos: Molduras Vinho/Verde, Fundo Caminho/Aurora, Chama Dourada, Moldura do Álbum, Tema Pentecostes e Tema Padroeiros.

Lúmens nunca compram XP.

## Conquistas

Primeiro Passo, Chama Acesa, Palavra Viva, Evangelho em Ação, Peregrino da Comunidade, Testemunhas, Colecionador, Comunhão, Padroeiros da Jornada, Advento, Retorno, Vida em Cristo, Oração e Missão e Às portas da Quaresma.

## Segurança econômica

Obrigatório no servidor:

- idempotência em missões, Centelhas, baús e marcos
- saldo nunca negativo
- nenhuma recompensa baseada apenas em JavaScript
- última carta não pode sair por presente/troca
- operações de troca são atômicas
- horários sensíveis usam America/Fortaleza
- duplicação de missão pelo catequista nasce desativada
- deploy incremental nunca apaga arquivos remotos

## Testes mínimos de release

- PHP syntax
- migrações idempotentes
- 22 etapas, 56 missões, 60 Centelhas, 14 badges, 9 baús
- 100 usuários simulados
- 2.000 tentativas de recompensa com apenas 1.000 aplicações válidas
- duplo clique em missão sem XP duplicado
- limite semanal de Intercessão
- Rosário com custo/cooldown
- presente semanal e bloqueio dos padroeiros
- três trocas por semana e aceite atômico
- baú aberto uma única vez
- nível Enviado em 1.800 XP
