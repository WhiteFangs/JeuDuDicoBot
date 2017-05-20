# Jeu du Dico Bot

A Twitter bot that tweets a definition of a rare french word and a poll with several words, one being the actual definition's word. 

The Twitter bot is [@JeuDuDicoBot](https://twitter.com/JeuDuDicoBot)

## How it works
The program looks for a word in the rare words database and for three other words with close lexical properties.
The tweet is the definition of the first word, the poll choices are made of all the words shuffled.
The program then posts the poll and the tweet containing the poll, and stores the tweet's id and the right word for the poll in a database.
Next time the program is run, it will begin by tweeting the right answer for the poll in reply to the poll's tweet.

## Post a Twitter poll
Posting a Twitter poll is currently not supported in the official Twitter Rest API, so you'll need to hack your way to post one. For that, you must have OAuth Access Tokens for an official Twitter application that supports polls, for instance Twitter for iPhone or Twitter for Android. 
In this program I used Android, but [@fourtonfish](https://github.com/fourtonfish) has made a [good example for iPhone in Python](https://gist.github.com/fourtonfish/5ac885e5e13e6ca33dca9f8c2ef1c46e).

  1. Create your Twitter bot's account
  2. Use [Twitter's PIN-based authorization](https://dev.twitter.com/oauth/pin-based) with the [chosen app's consumer keys](https://gist.github.com/shobotch/5160017) and your bot's account to get your official app's OAuth Tokens
  3. Create a [Twitter application](https://apps.twitter.com) for your bot and get your app's OAuth Tokens
  4. Create the card_data parameter of your call that looks like this:
```javascript
  card = {
	'twitter:string:choice1_label': 'choice1',
	'twitter:string:choice2_label': 'choice2',
	'twitter:string:choice3_label': 'choice3',
	'twitter:string:choice4_label': 'choice4',
	'twitter:long:duration_minutes': 1440,
	'twitter:api:api:endpoint': '1',
	'twitter:card': 'poll4choice_text_only', // pollXchoice_text_only if you have X choices (2 <= X <= 4)
    }
```
  5. Don't forget to stringify (or JSON encode) the card object.
  6. When you make the call to `https://caps.twitter.com/v2/cards/create.json`, use the user-agent of the official app you chose (in my example: `TwitterAndroid/6.45.0 Nexus 4/17 (LGE;mako;google;occam;0)`)


## Databases
It uses two databases: 
  - a database of rare french words with their definitions and lexical informations (part of speech, gender and number)
  - a database to store the id of a tweet containing the current poll and the right word for this poll

## License
The source code of this bot is available under the terms of the [MIT license](http://www.opensource.org/licenses/mit-license.php).

