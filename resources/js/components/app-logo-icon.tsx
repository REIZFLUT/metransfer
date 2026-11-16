import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.4 20.4 21.85 12.5a.5.5 0 0 0 0-.92L3.4 3.6a.5.5 0 0 0-.69.58L4.6 11l9.4 1-9.4 1-1.89 6.82a.5.5 0 0 0 .69.58Z" />
        </svg>
    );
}
