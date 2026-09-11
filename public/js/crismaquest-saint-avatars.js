(() => {
    'use strict';

    // CrismaQuest saint-avatar registry.
    // Faces are never generated or altered by AI here. Each avatar uses
    // an archival photograph or historical/devotional artwork hosted on Wikimedia Commons.
    // CrismaQuest only applies the shared circular crop/frame in the interface.
    const saints = {
        'São Carlo Acutis': { image: '/assets/crismaquest/saints/sao-carlo-acutis-user.jpg', position: '50% 34%' },
        'Santa Teresinha do Menino Jesus': { image: '/assets/crismaquest/saints/santa-teresinha-menino-jesus.jpg', position: '50% 35%' },
        'São Francisco de Assis': { image: '/assets/crismaquest/saints/sao-francisco-assis-user.jpg?v=20260911j', position: '50% 28%' },
        'São Pedro': { image: '/assets/crismaquest/saints/sao-pedro.jpg', position: '50% 28%' },
        'Santa Faustina Kowalska': { image: '/assets/crismaquest/saints/santa-faustina-kowalska.png', position: '50% 30%' },
        'São João Paulo II': { image: '/assets/crismaquest/saints/sao-joao-paulo-ii.jpg', position: '50% 28%' },
        'Santa Gianna Beretta Molla': { image: '/assets/crismaquest/saints/santa-gianna-beretta-molla.jpg', position: '50% 32%' },
        'Santo Agostinho': { image: '/assets/crismaquest/saints/santo-agostinho.jpg', position: '50% 28%' },
        'Santa Mônica': { image: '/assets/crismaquest/saints/santa-monica.jpg', position: '50% 28%' },
        'São José': { image: '/assets/crismaquest/saints/sao-jose-user.jpg?v=20260911j', position: '50% 30%' },
        'São Vicente de Paulo': { image: '/assets/crismaquest/saints/sao-vicente-de-paulo.jpg', position: '50% 28%' },
        'São Sebastião': { image: '/assets/crismaquest/saints/sao-sebastiao.jpg', position: '50% 22%' },
        'Santa Joana d’Arc': { image: '/assets/crismaquest/saints/santa-joana-darc.jpg', position: '50% 28%' },
        'São Paulo': { image: '/assets/crismaquest/saints/sao-paulo.jpg', position: '50% 28%' },
        'Santa Clara de Assis': { image: '/assets/crismaquest/saints/santa-clara-assis.jpg', position: '50% 28%' },
        'Santa Catarina de Sena': { image: '/assets/crismaquest/saints/santa-catarina-sena.jpg', position: '50% 27%' },
        'São João Bosco': { image: '/assets/crismaquest/saints/sao-joao-bosco.jpg', position: '50% 28%' },
        'Santa Teresa de Calcutá': { image: '/assets/crismaquest/saints/santa-teresa-calcuta.jpg', position: '50% 28%' },
        'Santo Antônio de Pádua': { image: '/assets/crismaquest/saints/santo-antonio-padua.webp', position: '50% 27%' },
        'São Domingos Sávio': { image: '/assets/crismaquest/saints/sao-domingos-savio.jpg', position: '50% 30%' },
        'São Pier Giorgio Frassati': { image: '/assets/crismaquest/saints/sao-pier-giorgio-frassati.jpg', position: '50% 30%' },
        'São João Evangelista': { image: '/assets/crismaquest/saints/sao-joao-evangelista.jpg', position: '50% 27%' },
        'Santo André': { image: '/assets/crismaquest/saints/santo-andre.jpg', position: '50% 28%' },
    };

    // Backward-compatible aliases while the bootstrap canonicalizes existing rows.
    saints["Santa Joana d'Arc"] = saints['Santa Joana d’Arc'];
    saints['Santa Clara'] = saints['Santa Clara de Assis'];
    saints['Santo Antônio'] = saints['Santo Antônio de Pádua'];
    saints['Beato Pier Giorgio Frassati'] = saints['São Pier Giorgio Frassati'];

    const normalize = (value) => (value || '').replace(/\s+/g, ' ').trim();

    function installImage(avatar, saintName) {
        const config = saints[saintName];
        if (!avatar || !config || avatar.dataset.cqSaintImage === saintName) return;

        avatar.dataset.cqSaintImage = saintName;
        avatar.style.position = 'relative';
        avatar.style.overflow = 'hidden';
        avatar.style.borderRadius = '50%';
        avatar.style.background = 'linear-gradient(145deg, #f7edd6, #d9b56e)';
        avatar.style.boxShadow = '0 0 0 2px #d7b56d, 0 0 0 5px rgba(255,250,241,.92)';

        const fallbackIcon = avatar.querySelector('i');
        const img = document.createElement('img');
        img.src = config.image;
        img.alt = saintName;
        img.loading = 'lazy';
        img.decoding = 'async';
        img.referrerPolicy = 'no-referrer';
        img.style.position = 'absolute';
        img.style.inset = '0';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.style.objectPosition = config.position;
        img.style.borderRadius = '50%';
        img.style.display = 'block';

        img.addEventListener('load', () => {
            if (fallbackIcon) fallbackIcon.style.visibility = 'hidden';
        }, { once: true });

        img.addEventListener('error', () => {
            img.remove();
            if (fallbackIcon) fallbackIcon.style.visibility = '';
            delete avatar.dataset.cqSaintImage;
        }, { once: true });

        avatar.appendChild(img);
    }

    function applySaintAvatars() {
        document.querySelectorAll('.cq-student-shell .cq-card').forEach((card) => {
            const heading = card.querySelector('h3');
            const saintName = normalize(heading?.textContent);
            if (!saints[saintName]) return;
            installImage(card.querySelector('.cq-avatar'), saintName);
        });

        const hero = document.querySelector('.cq-hero-content');
        if (hero) {
            const saintStrong = Array.from(hero.querySelectorAll('.cq-motto strong'))
                .find((el) => saints[normalize(el.textContent)]);
            const saintName = normalize(saintStrong?.textContent);
            if (saints[saintName]) installImage(hero.querySelector('.cq-avatar'), saintName);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySaintAvatars, { once: true });
    } else {
        applySaintAvatars();
    }
})();