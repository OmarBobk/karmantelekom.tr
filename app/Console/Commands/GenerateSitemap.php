<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\SitemapGenerator;
use Symfony\Component\Console\Command\Command as CommandAlias;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.xml file';


    /**
     * Execute the console command.
     */
    public function handle()
    {

        $path = public_path('sitemap.xml');
        SitemapGenerator::create('https://karmantelekom.tr')->writeToFile($path);

        $this->info('Sitemap generated successfully.');

        return CommandAlias::SUCCESS;

    }
}
