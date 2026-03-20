import base64, os, re

html_path = os.path.join(os.path.dirname(__file__), 'index.html')
logos_dir = os.path.join(os.path.dirname(__file__), 'logos')

with open(html_path, 'r', encoding='utf-8') as f:
    content = f.read()

def embed(fn_name, img_path, w, h, style_extra=''):
    if not os.path.exists(img_path):
        print(f'SKIP: {img_path} not found')
        return content
    b64 = base64.b64encode(open(img_path, 'rb').read()).decode()
    uri = f'data:image/png;base64,{b64}'
    new_fn = f'function {fn_name}() {{ return `<img src="{uri}" style="width:{w}px;height:{h}px;object-fit:contain;display:block{style_extra}" alt="{fn_name}" />`; }}'
    # Replace existing function
    pattern = rf'function {fn_name}\s*\(\s*\)\s*\{{[^}}]*\}}'
    if re.search(pattern, content):
        result = re.sub(pattern, new_fn, content)
        print(f'OK: {fn_name} embedded ({len(b64)} chars base64)')
        return result
    else:
        print(f'NOT FOUND: {fn_name} - adding it')
        return content

content = embed('sgGroupLogoHTML', os.path.join(logos_dir, 'sg_group.png'), 59, 59)
content = embed('nirixLogoHTML',   os.path.join(logos_dir, 'nirix.png'),    32, 32)

with open(html_path, 'w', encoding='utf-8') as f:
    f.write(content)

print('Done.')
