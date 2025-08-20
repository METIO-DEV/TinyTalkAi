<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::hex('#000000'),
                'gray' => Color::hex('#CDCDCD'),
                'danger' => Color::Rose,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->favicon(asset('TinyTalkAi_Logo.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName('TinyTalkAI Admin')
            ->sidebarWidth('18rem') // Largeur plus importante pour la sidebar
            ->collapsibleNavigationGroups(false) // Groupes toujours développés
            ->navigationItems([
                NavigationItem::make('Dashboard')
                    ->icon('heroicon-o-home')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.dashboard'))
                    ->url(fn (): string => \Filament\Pages\Dashboard::getUrl()),
            ])
            ->maxContentWidth('7xl')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                'panels::styles.before',
                fn (): string => '
                    <style>
                        /* Styles personnalisés pour la sidebar */
                        .fi-sidebar {
                            color: white !important;
                            transition: all 0.3s ease;
                        }
                        
                        .fi-sidebar-item {
                            margin-bottom: 0.5rem;
                            transition: all 0.3s ease;
                        }
                        
                        .fi-sidebar-item:hover {
                            transform: translateX(5px);
                        }
                        
                        .fi-sidebar-item-active {
                            border-left: 3px solid white;
                        }
                        
                        /* Amélioration du contraste des textes */
                        .fi-sidebar-item-label {
                            color: white !important;
                            font-weight: 500;
                        }
                        
                        /* Style du header de la sidebar */
                        .fi-sidebar-header {
                            border-bottom: 1px solid #333333;
                            justify-content: start;
                            display: flex;
                            text-align: start;
                        }

                        .fi-logo{
                            display: flex;
                            align-items: center;
                        }
                        
                        /* Responsive fixes */
                        @media (max-width: 768px) {
                            .fi-sidebar {
                                width: 100% !important;
                                max-width: 100% !important;
                            }
                        }
                    </style>
                '
            );
    }
}
