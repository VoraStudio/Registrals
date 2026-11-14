<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Blog\BlogRepository;
use App\Service\Blog\BlogTranslationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:blog:pretranslate',
    description: 'Pre-tradueix tots els articles Markdown del blog a ES/EN desant-los a la memòria cau per hash SHA-256'
)]
class BlogPretranslateCommand extends Command
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly BlogTranslationService $translationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('lang', null, InputOption::VALUE_OPTIONAL, 'Idioma concret (es o en). Si s\'omet, tradueix tots dos.', null)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Força la retraducció ignorant la memòria cau existent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $optLang = $input->getOption('lang');
        $force = (bool) $input->getOption('force');
        $langs = $optLang !== null ? [(string) $optLang] : ['es', 'en'];

        $posts = $this->blogRepository->getPublishedPosts(includeFuture: true);
        if ($posts === []) {
            $io->warning('No s\'han trobat articles publicats a content/blog.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Pre-traducció del Blog Registrals (%d articles)', count($posts)));

        foreach ($posts as $summaryPost) {
            $post = $this->blogRepository->getPostBySlug($summaryPost->slug, includeFuture: true);
            if ($post === null) {
                continue;
            }

            foreach ($langs as $lang) {
                $io->write(sprintf('  • [%s] "%s" -> %s ... ', $post->slug, mb_substr($post->title, 0, 45), strtoupper($lang)));
                $res = $this->translationService->translatePost($post, $lang, $force);

                if ($res['from_cache']) {
                    $io->writeln('<info>CACHE HIT</info>');
                } elseif ($res['fallback']) {
                    $io->writeln('<comment>FALLBACK (Node SGLang no disponible)</comment>');
                } else {
                    $io->writeln('<info>TRADUÏT I DESAT AL HASH</info>');
                }
            }
        }

        $io->success('Procés de pre-traducció finalitzat.');
        return Command::SUCCESS;
    }
}

