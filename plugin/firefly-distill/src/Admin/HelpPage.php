<?php

namespace FireflyDistill\Admin;

final class HelpPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        echo '<div class="wrap ffd-wrap"><h1>Ajuda interna</h1>';
        echo '<ul>';
        echo '<li>MVP: institucionais ≤ 50 URLs por host.</li>';
        echo '<li>Visitante anônimo recebe HTML em <code>current/{host}/</code>. Logado, POST, wp-admin e bypass caem no WP.</li>';
        echo '<li>Bypass: <code>?firefly-distill=origin&amp;ff_sig=&amp;ff_exp=</code> assinado. Token de um host não vale em outro.</li>';
        echo '<li>Incavel: o proxy host→página atual permanece. O distill só adiciona try_files antes.</li>';
        echo '<li>Site com um WP: o host primário nasce da ativação (<code>home_url()</code>).</li>';
        echo '<li>O plugin não recarrega Nginx e não gera HTML nos hooks.</li>';
        echo '</ul></div>';
    }
}
