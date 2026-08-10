<li <?php post_class( 'article' ); ?>>
    <header class="article__head">
        <?php if ( has_post_thumbnail() ) : ?>
            <div class="article__thumbnail">
                <?php the_post_thumbnail(); ?>
            </div>
        <?php endif; ?>

        <h2 class="article__title">
            <a href="<?php the_permalink(); ?>" rel="bookmark" title="<?php echo esc_attr( app_get_permalink_title() ); ?>">
                <?php the_title(); ?>
            </a>
        </h2>

        <?php Theme::partial( 'post-meta' ); ?>
    </header>

    <div class="article__body">
        <div class="article__entry">
            <?php the_excerpt(); ?>
        </div>
    </div>
</li>
