<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\RateLimiter;
use Amigo\Core\View;
use Amigo\Service\Social;

/** Freunde-Seite und Aktionen: Anfragen, Freundschaften, Blockieren, Likes. */
final class SocialController
{
    /** Eigene Übersicht: offene Anfragen, Freunde mit Online-Status, blockierte Nutzer. */
    public function index(): void
    {
        $uid = (int)Auth::id();
        $friends = Social::friends($uid);
        foreach ($friends as &$f) {
            $f['online'] = Social::isOnline($f);
        }
        unset($f);
        usort($friends, fn ($a, $b) => [$b['online'], strtolower($a['username'])] <=> [$a['online'], strtolower($b['username'])]);
        View::render('social/index', [
            'title'    => 'Freunde',
            'nav'      => 'community',
            'enabled'  => Social::enabled(),
            'friends'  => $friends,
            'incoming' => Social::incoming($uid),
            'outgoing' => Social::outgoing($uid),
            'blocked'  => Social::blockedByMe($uid),
            'user'     => Auth::user(),
        ]);
    }

    /** POST-Aktionen; Ziel per Benutzer-ID, Rücksprung zur Profilseite oder Freunde-Seite. */
    public function action(): void
    {
        $uid = (int)Auth::id();
        $back = in_array(input('back', 12), ['profile', 'collection'], true) ? input('back', 12) : 'friends';
        $target = App::db()->one(
            'SELECT id, username, collection_public, allow_friend_requests, allow_likes, is_active FROM {users} WHERE id = ?',
            [input_int('id', 1) ?? 0]
        );
        if (!is_post() || !$target || (int)$target['id'] === $uid) {
            redirect('friends');
        }
        $tid = (int)$target['id'];
        $action = input('action', 20);
        $goBack = static function () use ($back, $target): never {
            // nach dem Blockieren ist das Profil nicht mehr sichtbar
            if ($back !== 'friends' && !Social::isBlocked((int)Auth::id(), (int)$target['id'])) {
                redirect($back === 'collection' ? 'u/collection' : 'u', ['name' => $target['username']]);
            }
            redirect('friends');
        };
        if (RateLimiter::tooMany('social:' . $uid, 60, 600)) {
            flash('error', 'Zu viele Aktionen in kurzer Zeit – bitte kurz warten.');
            $goBack();
        }
        RateLimiter::hit('social:' . $uid);

        switch ($action) {
            case 'request':
                if ($problem = Social::requestProblem($uid, $target)) {
                    flash('error', $problem);
                } else {
                    $state = Social::request($uid, $target);
                    flash('ok', $state === 'friends' ? 'Ihr seid jetzt befreundet! 🎉' : 'Freundschaftsanfrage an ' . $target['username'] . ' gesendet.');
                }
                break;
            case 'accept':
                if (Social::enabled() && Social::accept($uid, $tid)) {
                    flash('ok', 'Du und ' . $target['username'] . ' seid jetzt befreundet! 🎉');
                }
                break;
            case 'decline':
                Social::removeRelation($uid, $tid);
                flash('ok', 'Anfrage von ' . $target['username'] . ' abgelehnt.');
                break;
            case 'cancel':
                Social::removeRelation($uid, $tid);
                flash('ok', 'Anfrage an ' . $target['username'] . ' zurückgezogen.');
                break;
            case 'remove':
                Social::removeRelation($uid, $tid);
                flash('ok', $target['username'] . ' ist nicht mehr in deiner Freundesliste.');
                break;
            case 'block':
                Social::block($uid, $tid);
                flash('ok', $target['username'] . ' ist blockiert. Ihr könnt euch nicht mehr sehen, schreiben oder liken.');
                break;
            case 'unblock':
                Social::unblock($uid, $tid);
                flash('ok', 'Blockierung von ' . $target['username'] . ' aufgehoben.');
                break;
            case 'like':
                if (Social::canLike($uid, $target)) {
                    $liked = Social::toggleLike($uid, $target);
                    flash('ok', $liked ? '❤ Du magst die Sammlung von ' . $target['username'] . '.' : 'Like zurückgenommen.');
                }
                break;
        }
        $goBack();
    }
}
