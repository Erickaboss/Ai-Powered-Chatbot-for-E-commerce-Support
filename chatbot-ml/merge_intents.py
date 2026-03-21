import json

with open('dataset/intents.json') as f:
    part1 = json.load(f)

with open('dataset/intents_part2.json') as f:
    part2 = json.load(f)

merged = {'intents': part1['intents'] + part2['intents_part2']}

with open('dataset/intents.json', 'w') as f:
    json.dump(merged, f, indent=2)

total = sum(len(i['patterns']) for i in merged['intents'])
print(f"Total intents : {len(merged['intents'])}")
print(f"Total patterns: {total}")
for i in merged['intents']:
    print(f"  {i['tag']:<22} {len(i['patterns'])} patterns")
