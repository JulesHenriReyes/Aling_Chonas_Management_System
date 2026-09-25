import glob
import re

for f in sorted(glob.glob('testsprite_tests/TC*.py')):
    content = open(f, encoding='utf-8').read()
    # Check if page.goto("http://localhost:8000/") is called and then directly tries to fill email without clicking Staff Login or going to /login
    pattern = r'await page\.goto\("http://localhost:8000/"\)(?:(?!Staff Login|goto\("http://localhost:8000/login"\)).)*?locator\("input\[name=\\"email\\"\]"\)'
    if re.search(pattern, content, re.DOTALL):
        print("NEEDS /login:", f)
